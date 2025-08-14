# File System Watcher - Assessment Task

## Additional Dependencies _(besides Laravel)_
* [laravel/horizon](https://laravel.com/docs/horizon)
* [react/event-loop](https://reactphp.org/event-loop/)
* [react/filesystem](https://github.com/reactphp/filesystem)
* [spatie/image](https://spatie.be/docs/image/v3/introduction)
* [laravel/pint](https://laravel.com/docs/pint)

## Installation
**NOTE:** Horizon only works with Redis, so you need to have a Redis server running and configured in your `.env` file.

1. Clone the repository
2. Copy `env.example` to `.env` and configure your environment variables _(mainly the database and queue connection)_
3. Run `composer app:setup` to set up the project

### How do run the file watcher
**TIP:** If you want to see all logs instantly run `tail -f storage/logs/laravel.log` in a separate terminal window.

1. Start the queue by running `php artisan queue:work` and keep it open
2. Start the file watcher by running `php artisan app:file-watcher` and keep it open
   * The watcher has some **optional arguments/options**:
     * `app:file-watcher [<path>]` // The path to watch for file changes. **[default: "./storage/app/private"]**
     * `--interval=<seconds>` // The interval in seconds to check for changes. **[default: "1.0"]**
3. **OPTIONAL:** Open the project in the browser - the homepage automatically redirects to the [Horizon dashboard](https://laravel.com/docs/horizon)

## Conceptual design

### Initial thoughts
* Preferring Laravel due to the ecosystem of user-friendly tools _(e.g. Horizon, Pint, etc.)_, the ease of use and handy helpers for the given task.
  * A Symfony app would look similar in code structure, but would require more configuration, setup and boilerplate.
  * A new project using Laravel is much faster up and running than using Symfony.
  * Time is of the essence here, so I preferred less boilerplate.
* Finding a way to **reactivly and recursively watch a directory** for changes. 
  * Found [ReactPHP](https://reactphp.org/) as a good fit for this task.
* Leveraging Laravel's **queue system** to handle file **processing asynchronously**.
    * This allows the watcher to **remain responsive** and handling **many changes without blocking**.
* Building some sort of **mapping between mimetypes and related jobs** to **easily extend** later on.
  * Each file type can have its own **job** that handles the individual processing.
  * For now a coded implementation is more than enough
    * It could be extended later to something more dynamic and/or user-configurable.
* Logging all changes for information and debugging purposes.
* Using some sort of queue monitoring
  * [Laravel Horizon](https://laravel.com/docs/horizon) will be a good starting-fit for this
* Using Pint _(basically a wrapper for CS-Fixer)_ for automated code formatting

### Why not using normal events + listeners?
* The watcher needs to be **reactive** and **handle many changes** without blocking.
* Events would be **too slow** and **not reactive enough**.
* In general not great for **high frequency changes** due to its nature of being **synchronous**.

## Challenges and Problems
* Finding a way to **reactively watch a directory** for changes.
  * ReactPHP's filesystem component is a good fit for this task.
* Appending a random paragraph to txt files will lead to the file watcher being triggered again
  * This lead to an infinite loop of file changes.
  * **Solution:** Temporarily caching the file path to lock the file
    * This way the main job `App\Jobs\FileWatcher\ProcessFileChanges` can check if the file is present in cache which means it was just updated from another job.
    * After finding a cache entry, no other job will be dispatched and the cached key will be removed, freeing the file for the next change.
* Newly created files would have the mimetype `application/x-empty` and would not process correctly
  * **Solution:** Using the file extension as fallback to re-mapped to the correct mimetype
    * This is done in the `App\Enums\SupportedFileTypes::mapFileExtensionToMimeType()` method


## Chosen solution
1. I'm using ReactPHP to watch for filesystem changes in a given interval _(App\Console\Commands\FileWatcherCommand)_
   * The watcher is implemented as a command that can be run in the background.
2. After detecting changes the command determines the type of change and dispatches the _**App\Jobs\FileWatcher\ProcessFileChanges**_ job
3. This job handles the further file processing
   * Checks if the detected file is a valid mimetype
   * Checks if the given mimetype has a matching job
     * This mapping could have been also done by registering the jobs in the service container and tagging it _(e.g. like [tagged_iterator in Symfony](https://symfony.com/doc/current/service_container/tags.html))_. For simplicity I just used a simple array mapping.
4. After finding the matching job it dispatches it to the queue
    * All **App\Jobs\FileWatcher\FileTypes\ProcessFileXY** jobs extend from the **App\Jobs\FileWatcher\Contracts\AbstractFileType** to have a common interface
5. The **App\Jobs\FileWatcher\FileTypes\ProcessFileXY** job now handles the file processing according to the requirements
    * **Created and Modified**
      * **JPG**
        * Optimizes image and stores it with a `optimized` prefix
      * **JSON**
        * Sends a POST request to `https://fswatcher.requestcatcher.com/` with the file content as payload
      * **TXT**
        * Fetches from `https://baconipsum.com/api/?type=meat-and-filler` and appends one random paragraph to the file
      * **ZIP**
        * Automatically un-archiving the archive into a directory named after the archive
        * All newly created files will trigger a file change which dispatches the corresponding jobs
        * Delete the original ZIP file
   * **Deleted**
     * Requests a meme from `https://meme-api.com/gimme` and replaces the deleted file with the meme image


## Extensibility considerations for future watchers
* New mimetype = new job
* Possibility to chain jobs for a specific mimetype using [queue batches](https://laravel.com/docs/queues#job-batching)
* Possibility to register jobs in the service container and tag them instead of mapping them in an array
* Possibility to register jobs in a config file
* Possibility to map jobs and mimetypes by user configuration and a database solution
* Splitting FileProcessing jobs into smaller chunks and letting the user configure which mimetype should process which job
  * **Examples:**
    * All image types could be optimized by multiple presets => each preset being one selectable option
    * Archives in general could be un-archived and then processed by multiple jobs, or be ignored entirely
* Sending notifications _(e.g Slack, Teams, Email)_ on job failure, after a pre-defined thresholds _(rate limiting)_ or on success
* Adding a user management _(either in this app or from an existing user base such as LDAP)_ to configure jobs based on user roles/permissions.
  * **Example:** Only users with permission `file:processing:optimize-images` will trigger the image optimization job
