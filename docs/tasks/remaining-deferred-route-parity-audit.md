# Remaining Deferred Route Parity Audit

Date: 2026-06-26

## Scope

This audit reviews the next-candidate deferred routes after the return-order reverse stock/finance block. The goal is to avoid replacing controlled-deferred compatibility wrappers with real behavior when the Java reference does not expose the route or the copied frontend branch is inactive.

## Findings

- No-account return auto-refund is not a Java-parity target. `BizSaleProjectReturnProductApproveDelegate` delegates to return-order creation, and `ReturnOrderServiceImpl.add()` leaves positive-amount returns unsettled. Refund settlement is triggered by later `ReturnAndRefund` expenditure records, not by creating an automatic no-account refund.
- `POST /biz/bizpaymentrecord/add|delete` and `POST /biz/bizexpenditurerecord/add|delete` were originally controlled-deferred because Java controllers expose read/edit/edit-account behavior, while direct add/delete routes are not public. On 2026-06-26, the user explicitly opened finance direct CRUD as product behavior; ThinkPHP now exposes bounded manual add/delete for these two modules, with settlement-backed add and guarded `Process_sys` delete that reverses linked statements and account balances.
- `POST /biz/bizcollectionreceipt/add|edit|delete` and `POST /biz/bizdebitnote/add|edit|delete` were originally controlled-deferred because Java controllers keep those CRUD methods commented out; the exposed Java behavior is mark-success, batch repayment/expenditure, and debit-note history add. On 2026-06-26, the user explicitly opened finance direct CRUD as product behavior; ThinkPHP now exposes bounded collection/debit row maintenance with payment/expenditure binding guards and no direct account-balance or statement side effects.
- `GET /biz/task/sse/stream` should stay controlled-deferred. Java `BizTaskController` imports `SseEmitter` but does not expose a task SSE route. The active frontend refresh path uses `/dev/message/createSseConnect` and `FlushProcessNotice`, which is already covered by the short-lived message SSE compatibility route.
- `POST /gen/config/add` should stay controlled-deferred. Java `GenConfigController` exposes list, detail, edit, delete, and editBatch, but no add route.
- `POST /biz/saleprojectproductitem/add|edit|delete` should not be expanded beyond the current guarded `FOLLOW` maintenance. Java has those controller routes commented out; only `mark/edit` is public. Non-`FOLLOW` mutation remains a product decision because it intersects invoice, delivery, return, stock, and project-state side effects.

## Next Candidate Class

Do not choose a wrapper only because it appears in the copied Vue API file. Pick the next feature from one of these categories:

- A Java-public route or workflow behavior that is still unavailable in ThinkPHP.
- A visible frontend workflow with an active caller and a bounded side-effect map.
- Deployment/runtime hardening.
- Final production data-sync planning, only after the user explicitly approves that stage.

Each new feature block still needs a module-specific dependency map, permission model, transaction and rollback strategy, side-effect map, and smoke plan before replacing controlled-deferred behavior.
