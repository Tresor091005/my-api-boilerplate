# TODO - Universal Tags

## Tag management API

- [x] Add dedicated API routes for managing the tag repository without using
  `attach` on a model.
- [x] Add a bulk endpoint for creating tags by type.
- [ ] Add a tag update endpoint to support renaming.
- [ ] Add a tag merge endpoint.
- [ ] Add a tag deletion endpoint.
- [ ] Prevent deletion of a tag that is still used by at least one `taggable`.
- [ ] Confirm the business rule: `detach` only dissociates the tag from the
  model and never deletes the tag itself.
- [ ] Add an endpoint for ordering tags of a type through `order_col`.

## Model utilities

- [ ] Add `hasTagOfType`, `hasAnyTagsOfType`, and `hasAllTagsOfType` utilities.
- [ ] Add a utility for targeted loading of tags of a type.

## Business rules to preserve

- [ ] Keep the type as the central axis for read and write operations.
- [ ] Do not allow deletion of a tag that is in use.
- [ ] Do not couple `detach` with automatic tag deletion.
