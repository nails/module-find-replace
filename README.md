# Nails Find & Replace

Search and replace across text columns in a Nails application. Apps register exclusions and resolvers. The module provides the engine, stored searches, and the admin UI.

Console commands:

- `find-replace:search`
- `find-replace:replace`
- `find-replace:revert`
- `find-replace:fields`

Install with Composer:

```bash
composer require nails/module-find-replace
```

Admin searches are queued. `FIND_REPLACE_QUEUE` chooses the queue; when it is unset the search uses `default`. The Find & Replace index warns when that queue has no live worker, using the same check as the queue dashboard alert.

See [the documentation](https://docs.nailsapp.co.uk/modules/find-replace).
