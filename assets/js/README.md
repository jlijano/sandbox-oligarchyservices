# JavaScript assets

Place shared browser scripts here for the public site.

Current compatibility note: the live pages still load the existing flat `/assets/*.js` files. New JavaScript work should be added here first, then individual page references can be migrated after visual and interaction checks.

Recommended layout:

- `analytics.js`: analytics loader, nav enhancement, loader behavior, current-year footer, and optional tracking hooks.
- `service-order.js`: homepage service order and homepage About preview injection.
- `process-interaction.js`: homepage process-step tab behavior.
