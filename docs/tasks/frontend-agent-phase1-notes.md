# frontend-agent Phase 1 Notes

## Goal

Document frontend compatibility requirements before any frontend code is copied or edited.

## Confirmed Observations

- The original frontend is under the Java OA source tree and is read-only for current work.
- API requests are funneled through `src/utils/request.js`.
- Login menu data is requested from `src/api/sys/userCenterApi.js` with `loginMenu`.
- Button visibility depends on `hasPerm()` and `USER_INFO.buttonCodeList`.
- File downloads, uploads, SSE, and safe password checks have special frontend handling.

## First Safe Adaptation Queue

1. Confirm backend supports login token and login menu response expected by the frontend.
2. Decide header compatibility: `token` versus `Authorization`.
3. Decide response message compatibility: `msg` versus `message`.
4. Map user/org/position frontend APIs after user-agent read-only endpoints exist.
5. Defer upload/download/SSE until corresponding backend modules are ready.

## Final Merge Requirement

All frontend-agent commits must eventually merge into `refactor/thinkphp-main` through the merge-agent. Frontend source edits require a separate editable frontend location decision first.
