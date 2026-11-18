# PHP core

`Domain` contains pure rules and entities. `Application` contains use cases and read/write ports. `Infrastructure` implements the ports and holds persistence adapters. Dependencies point inward. Domain and application code must not load HTTP or initialize PDO.

`autoload.php` is declaration-only composition support. `legacy-functions.php` loads existing feature facades while they are migrated; their function names remain compatible. They are deliberately not disguised as domain classes.

Read `../context/source-architecture.md` for editing paths, transaction constraints and tests.
