<?php

namespace App\Console\Commands;

use App\Actions\GetCoordinatesAction;
use App\Actions\GetWeatherAction;
use App\Models\Message;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;

class WeatherCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:weather-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private ?User $user = null;

    private Collection $messages;

    private string $model = 'claude-3-5-sonnet-20241022';

    private array $tools = [];

    public function handle()
    {
        $this->initUser();
        $this->initConversation();
        $this->initTools();

        $question = $this->ask('You are using the Weather CLI app, what can I do for you?');

        while ($question !== 'exit') {
            $this->handleChat($question);

            $question = $this->ask('What else would you ask me?');
        }
    }

    private function handleChat(string $question, string $type = 'user'): void
    {
        $this->user->messages()->create([
            'content' => $question,
            'type' => $type,
        ]);
        $this->messages->push(
            new UserMessage($question)
        );

        $response = Prism::text()
            ->using(Provider::Anthropic, $this->model)
            ->withMessages($this->messages->all())
            ->withTools($this->tools)
            ->asText();

        // this measn no toolCalls are triggered
        if (empty($response->toolCalls)) {
            $this->user->messages()->create([
                'content' => $response->text,
                'type' => 'assistant',
            ]);

            $this->messages->push(
                new AssistantMessage($response->text)
            );

            $this->info('Assistant: '.$response->text);

        } else {
            // handles toolCalls recursively until they resolve their dependencies
            // ex: user wants to get weather for "London", but this tool needs to resolve London's coordinates
            // using the 'get_coordinates' tool.
            $this->handleToolCalls($response);
        }
    }

    private function handleToolCalls($response): void
    {
        $this->user->messages()->create([
            'content' => $response->text,
            'type' => 'assistant',
            'tool_calls' => collect($response->toolCalls)->map(function (ToolCall $toolCall) {
                return [
                    'id' => $toolCall->id,
                    'name' => $toolCall->name,
                    'arguments' => $toolCall->arguments(),
                ];
            }),
        ]);

        $this->messages->push(
            new AssistantMessage($response->text, $response->toolCalls)
        );

        collect($response->toolResults)->each(function (ToolResult $toolResult) {
            $this->user->messages()->create([
                'content' => json_encode([
                    'toolCallId' => $toolResult->toolCallId,
                    'toolName' => $toolResult->toolName,
                    'args' => $toolResult->args,
                    'result' => $toolResult->result,
                ]),
                'type' => 'tool',
            ]);
        });

        $this->messages->push(new ToolResultMessage($response->toolResults));

        $response = Prism::text()
            ->using(Provider::Anthropic, $this->model)
            ->withMessages($this->messages->all())
            ->withTools($this->tools)
            ->asText();

        $this->info('Assistant: '.$response->text);

        if (! empty($response->toolCalls)) {
            $this->handleToolCalls($response);
        }
    }

    private function initUser()
    {
        $attempts = 0;

        while (! $this->user) {
            $userEmail = $this->ask('What is your email address?');

            $this->user = User::query()
                ->where('email', $userEmail)
                ->first();

            if ($this->user) {
                $this->info('Nice to see you again, '.$this->user->name);
                break;
            }

            if ($attempts === 2) {
                $this->fail(new \Exception('Maximum login attempts exceeded'));
            }

            $attempts++;

            $this->warn('No user with such mail found, try again.');
        }
    }

    private function initConversation()
    {
        $systemMessageInstruction = view('system-prompt')->render();

        $this->messages = collect();

        $this->messages->push(
            str($this->model)->contains('claude') ?
                new UserMessage($systemMessageInstruction) :
                new SystemMessage($systemMessageInstruction)
        );

	    $this->messages = $this->messages->concat(Message::query()->latest()->get()->map(function ($message) {
		    return match ($message->type) {
			    'user' => new UserMessage($message->content),
			    'assistant' => new AssistantMessage(
				    $message->content,
				    collect(json_decode($message->tool_calls, true))->map(function ($toolCall) {
					    return new ToolCall(
						    id: $toolCall['id'],
						    name: $toolCall['name'],
						    arguments: $toolCall['arguments'],
					    );
				    })->all()
			    ),
			    'tool' => (function() use ($message){
				    $data = json_decode($message->content, true);

				    return new ToolResultMessage([
					    new ToolResult(
						    toolCallId: $data['toolCallId'],
						    toolName: $data['toolName'],
						    args: $data['args'],
						    result: $data['result'],
					    )
				    ]);
			    })(),
			    default => new UserMessage($message->content),
		    };
	    }));
    }

    private function initTools()
    {
        $this->tools = [
            Tool::as('get_weather')
                ->for('Get current weather conditions')
                ->withStringParameter(
                    'latitude',
                    'The latitude of the place we are querying weather data.'
                )
                ->withStringParameter(
                    'longitude',
                    'The longitude of the place we are querying weather data.'
                )
                ->using(function (string $latitude, string $longitude): string {
                    return json_encode(GetWeatherAction::handle($latitude, $longitude));
                }),
            Tool::as('get_coordinates')
                ->for('Get current weather conditions')
                ->withStringParameter('place', 'The city to get weather for')
                ->using(function (string $place): string {
                    $coordinates = GetCoordinatesAction::handle($place);

                    $this->info(
                        sprintf('The latitude and longitude for %s are: %s and %s respectively.', $place, $coordinates['latitude'], $coordinates['longitude'])
                    );

                    return json_encode($coordinates);
                }),
        ];
    }
}
