<?php

// Module updates on a self-hosted server. docs/self-hosting-build-guide.md §5.2

return [

    'title' => 'Updates',
    'description' => 'New versions of your modules, from your provider.',
    'check_now' => 'Check now',
    'install_all' => 'Install all',
    'install' => 'Install',
    'install_confirm' => 'Install these updates now? A database backup is made first.',
    'mode' => 'Updates are installed',
    'mode_auto' => 'Automatically, within the hour',
    'mode_manual' => 'When you click Install',
    'mode_hint' => 'Set by your provider.',
    'last_check' => 'Last checked',
    'never' => 'Never',
    'version' => 'Core version',
    'available' => 'Available',
    'none_available' => 'Everything is up to date.',
    'new_module' => 'New module',
    'history' => 'History',
    'no_history' => 'No updates installed yet.',
    'modules_not_writable' => 'The Modules folder cannot be written by the web server, so updates cannot be installed. Ask your host to make it writable.',

    'result_success' => 'installed',
    'result_failed' => 'failed',
    'result_rolled_back' => 'failed and was undone',

    // Messages
    'not_linked' => 'This server is not linked to an update server.',
    'up_to_date' => 'Everything is up to date.',
    'n_available' => '{1} One update available.|[2,*] :count updates available.',
    'installed' => 'Installed: :modules.',
    'some_failed' => 'Some updates failed; see the history below. Nothing else was changed.',
    'nothing_to_install' => 'There is nothing to install. Check again first.',
    'already_running' => 'Updates are already being installed. Try again in a few minutes.',
    'requirement_failed' => 'Skipped: a module it needs failed to update.',
    'check_failed' => 'Could not check for updates: :error',
    'server_said' => 'The update server answered :status. :message',
    'bad_signature' => 'The answer from the update server is not signed by your provider. Nothing was changed.',
    'bad_answer' => 'The answer from the update server does not match this request. Nothing was changed.',
    'clock_skew' => 'This server\'s clock is more than 10 minutes off. Fix the time and try again.',
    'download_failed' => 'Download failed: :error',
    'hash_mismatch' => 'The downloaded file is damaged (checksum mismatch).',
    'bad_package_signature' => 'The package is not signed by your provider.',
    'not_allowed' => ':module cannot be installed on this server.',
    'manifest_mismatch' => 'The package does not contain the module and version it should.',
    'zip_unreadable' => 'The package cannot be opened.',
    'zip_rejected' => 'The package was refused (:reason).',
    'move_failed' => 'Could not write :path.',

];
