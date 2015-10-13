The **Platform.sh CLI** is the official command-line interface for [Platform.sh](https://platform.sh). Use this tool to interact with your [Platform.sh](https://platform.sh) projects, and to build them locally for development purposes.

[![Build Status](https://travis-ci.org/platformsh/platformsh-cli.svg)](https://travis-ci.org/platformsh/platformsh-cli) [![Latest Stable Version](https://poser.pugx.org/platformsh/cli/v/stable)](https://github.com/platformsh/platformsh-cli/releases) [![License](https://poser.pugx.org/platformsh/cli/license)](https://github.com/platformsh/platformsh-cli/blob/master/LICENSE)

### Requirements

* Operating system: Linux, OS X, Windows Vista, Windows 7 (any), or Windows 8 Pro (Win8 Standard does not work due to an issue with symlink permissions)
* PHP 5.4.0 or higher, with cURL support
* [Composer](https://getcomposer.org/)
* [Drush](https://github.com/drush-ops/drush) (only needed for Drupal projects)

### Installation

* [Install Composer globally](https://getcomposer.org/doc/00-intro.md#globally).

* Install the latest stable version of the CLI:

        composer global require platformsh/cli:@stable

* Make sure Composer's `vendor/bin` directory is in your system's PATH.

  In Linux or OS X, add this line to your [shell configuration
  file](#shell-configuration-file):

        export PATH="$PATH:$HOME/.composer/vendor/bin"

  In Windows, you can use this command from a Command Prompt (cmd.exe):

        setx PATH "%PATH%;%APPDATA%\Composer\vendor\bin"

  Start a new shell before continuing.

* Enable auto-completion and shell aliases (optional, but recommended).

  In Linux or OS X, add this line to your [shell configuration
  file](#shell-configuration-file):

        . "$HOME/.composer/vendor/platformsh/cli/platform.rc" 2>/dev/null

  In Windows, it would be:

        . "$APPDATA/Composer/vendor/platformsh/cli/platform.rc" 2> nul

#### Shell configuration file
Your 'shell configuration file' might be in any of the following
locations:

* `~/.bashrc` (common in Linux)
* `~/.bash_profile` (common in OS X)
* `~/.zshrc` (if using ZSH)

Start a new shell or type `source <filename>` to load the new configuration.

### Updating

New releases of the CLI are made regularly. Update with this command:

    composer global update

### Usage

You can run the Platform.sh CLI in your shell by typing `platform`.

        platform

Use the 'list' command to get a list of available options and commands:

        platform list

### Commands

The current output of `platform list` is as follows:

```
Platform.sh CLI

Global options:
  --help           -h Display this help message
  --quiet          -q Do not output any message
  --verbose        -v|vv|vvv Increase the verbosity of messages
  --version        -V Display this application version
  --yes            -y Answer "yes" to all prompts
  --no             -n Answer "no" to all prompts
  --shell          -s Launch the shell

Available commands:
  docs                                      Open the Platform.sh online documentation
  help                                      Displays help for a command
  list                                      Lists commands
  self-update (up)                          Update the CLI to the latest version
  web                                       Open the Platform.sh Web UI
account
  account:info                              View your account information
  account:login (login)                     Log in to Platform.sh
  account:logout (logout)                   Log out of Platform.sh
activity
  activity:list (activities)                Get the most recent activities for an environment
  activity:log                              Display the log for an environment activity
domain
  domain:add                                Add a new domain to the project
  domain:delete                             Delete a domain from the project
  domain:list (domains)                     Get a list of all domains
environment
  environment:activate                      Activate an environment
  environment:branch (branch)               Branch an environment
  environment:checkout (checkout)           Check out an environment
  environment:delete                        Delete an environment
  environment:drush (drush)                 Run a drush command on the remote environment
  environment:http-access (httpaccess)      Update HTTP access settings for an environment
  environment:info                          Read or set properties for an environment
  environment:list (environments)           Get a list of all environments
  environment:merge (merge)                 Merge an environment
  environment:relationships (relationships) List an environment's relationships
  environment:routes (routes)               List an environment's routes
  environment:set-remote                    Set the remote environment to track for a branch
  environment:sql (sql)                     Run SQL on the remote database
  environment:sql-dump (sql-dump)           Create a local dump of the remote database
  environment:ssh (ssh)                     SSH to the current environment
  environment:synchronize (sync)            Synchronize an environment
  environment:url (url)                     Get the public URL of an environment
integration
  integration:add                           Add an integration to the project
  integration:delete                        Delete an integration from a project
  integration:get (integrations)            View project integration(s)
  integration:update                        Update an integration
local
  local:build (build)                       Build the current project locally
  local:clean (clean)                       Remove old project builds
  local:drush-aliases (drush-aliases)       Find the project's Drush aliases
  local:init (init)                         Create a local project file structure from a Git repository
project
  project:delete                            Delete a project
  project:get (get)                         Clone and build a project locally
  project:info                              Read or set properties for a project
  project:list (projects)                   Get a list of all active projects
snapshot
  snapshot:create                           Make a snapshot of an environment
  snapshot:list (snapshots)                 List available snapshots of an environment
  snapshot:restore                          Restore an environment snapshot
ssh-key
  ssh-key:add                               Add a new SSH key
  ssh-key:delete                            Delete an SSH key
  ssh-key:list (ssh-keys)                   Get a list of SSH keys in your account
user
  user:add                                  Add a user to the project
  user:delete                               Delete a user
  user:list (users)                         List project users
  user:role                                 View or change a user's role
variable
  variable:delete                           Delete a variable from an environment
  variable:get (variables, vget)            Get a variable for an environment
  variable:set (vset)                       Set a variable for an environment
```

### Known issues

#### Caching
The CLI caches details of your projects and their environments. These caches
could become out-of-date. You can get a fresh list of projects or environments
with the `platform projects` and `platform environments` commands.

### Customization

You can configure the CLI via these environment variables:

* `PLATFORMSH_CLI_API_TOKEN`: an API token to use for all requests
* `PLATFORMSH_CLI_DEBUG`: set to 1 to enable cURL debugging
* `PLATFORMSH_CLI_DISABLE_CACHE`: set to 1 to disable caching
* `PLATFORMSH_CLI_DRUSH`: configure the Drush executable to use (default 'drush')
* `PLATFORMSH_CLI_ENVIRONMENTS_TTL`: the cache TTL for environments, in seconds (default 600)
* `PLATFORMSH_CLI_PROJECTS_TTL`: the cache TTL for projects, in seconds (default 3600)
* `PLATFORMSH_CLI_SESSION_ID`: change user (default 'default')
* `http_proxy` or `https_proxy`: specify a proxy for connecting to Platform.sh
