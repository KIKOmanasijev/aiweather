<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

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

	/**
     * Execute the console command.
     */
    public function handle()
    {
        $this->initUser();
		$this->initConversation();

	    $question = $this->ask("You are using the Weather CLI app, what can I do for you?");

        while ($question != 'exit') {
	        $this->user->messages()->create([
		        'content' => $question,
		        'type'    => 'user'
	        ]);
	        $this->messages->push(
		        new UserMessage($question)
	        );

            $response = Prism::text()
                ->using(Provider::Anthropic, $this->model)
                ->withMessages($this->messages->all())
                ->asText();

            $this->info($response->text);

	        $this->user->messages()->create([
		        'content' => $question,
		        'type'    => 'assistant'
	        ]);
	        $this->messages->push(
		        new AssistantMessage($question)
	        );

            $question = $this->ask('What else do you want to ask me?');
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

	private function initConversation() {
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
				'assistant' => new AssistantMessage($message->content),
				default => new UserMessage($message->content),
			};
		}));
	}
}
