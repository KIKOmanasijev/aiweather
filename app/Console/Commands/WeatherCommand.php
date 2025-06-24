<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;

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

    /**
     * Execute the console command.
     */
    public function handle()
    {
		$question = $this->ask("What do you want to ask me?");

        while ('exit' != $question){
	        $response = Prism::text()
                 ->using(Provider::Anthropic, 'claude-3-5-sonnet-20241022')
                 ->withPrompt($question)
                 ->asText();

			$this->info($response->text);

	        $question = $this->ask("What else do you want to ask me?");
        }
    }
}
