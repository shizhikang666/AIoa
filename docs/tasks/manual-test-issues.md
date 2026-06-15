# Manual Test Issues

Date started: 2026-06-15

This file tracks issues found during manual browser testing of the ThinkPHP OA migration. Add items here when a problem appears to be a product, frontend/backend compatibility, data-shape, or unfinished-route issue. Environment-only problems, such as a stopped local dev server, should be fixed directly and recorded only when they repeatedly block testing.

## Tracked Issues

| ID | Date | Area | Severity | Status | Issue | Evidence | Suggested Owner |
| --- | --- | --- | --- | --- | --- | --- | --- |
| MT-001 | 2026-06-15 | System > Organization Management > Add Organization | Medium | Fixed, browser recheck pending | In the add-organization drawer, the parent organization tree renders multiple nodes as `---` instead of readable organization names, making the parent selector ambiguous. | Root cause: org tree selector nodes exposed `label/title` but not the `name` field used by the copied TreeSelect mapping. Fix adds `name` to selector nodes and smoke coverage for `/sys/org/orgTreeSelector`. | frontend-agent / user-agent |

## Notes

- Likely impact: users cannot confidently choose the intended parent organization when creating or editing organizations.
- Suggested first check: compare the `/sys/org/orgTreeSelector` response fields with the Ant Design Vue tree-select field mapping used by `snowy-admin-web/src/views/sys/org/form.vue`.
