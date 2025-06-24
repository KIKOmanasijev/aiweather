## Introduction
A basic console app that lets users get weather information.

## How it works
The app is pretty simple, it has Users, and those users can have many Messages. 
When ever you run the app, you need to provide a valid email (from an account that already exists), 
and you have 3 attempts to do this.

Currently, there is no functionality to create accounts, so use the one created with the seeder.

Each chat message is saved in the database, and the chat can be restored every time 
you get back to the app.

## How to set up the app
1. Clone this repo
2. Install composer dependencies
3. Run the migrations with `php artisan migrate`
4. Run the seeder to create a fake account `php artisan db:seed`
5. Set up the `ANTHROPIC_API_KEY` env key.
6. Run the command with `php artisan app:weather-command`
7. Use the email address from the created fake account (test@example.com)
8. Start asking questions about the weather :)


## What did I NOT implement (yet)
- *Caching the coordinates for the cities.* (This is an easy step, but for the sake of simplicity and to save time, I skipped it)
- *Model picker* (A proper Laravel model picker, that will allow the user to pick the LLM model could be handy. To save time, I decided to not implement this feature. Laravel Prompts could be used to create fancy select elements)
- *Track tokens used*

## What the next dev should build next
- I would say the things I mentioned above. Caching the response for the cities could improve the response times, 
and a model picker would be handy to switch between LLM providers or/and models.
- An option to let users choose a date for which they want to get the weather data for. This implementation only allows users to fetch current weather data.
- Getting the forecast for the next X days could be also handy. Users with such data could ask questions from the type "When would be the best day in the next X days to get out on a long run?"

## Where they should extend the code
- initTools() - to define extra tools
- custom Action classes - to implement the logic
- adding an extra method to trigger a "select" dropdown using Laravel prompts to select a model/LLM provider.

## Any design decisions or caveats
1. I love using the Action pattern, therefore whenever I build web apps, I tend always have really slim controller methods, with:
    1. CustomRequest class to handle the validation
    2. Single Action class that contains the business logic. 
2. I tend to use DTOs and VOs in my apps. Therefore, I loved using the built-in VOs from Prism, such as: AssistantMessage, UserMessage, ToolCall and etc. 
3. I never use native functions to iterate or/and manipulate Iterable types. I ALWAYS use Laravel's collections. 
4. I use views to store large strings (especially if I have dynamic parts in it, such as {{name}} and etc).
5. You will probably notice me using the query() method (I always start with `Model::query()`) to start chaining up the Eloquent methods. I do this because of the better IDE autocomplete. 
