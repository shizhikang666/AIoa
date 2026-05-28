```powershell
Set-Location F:\AI\projects\testJava\OA-ThinkPHP
git status --short
git switch -c refactor/thinkphp-main
git worktree add F:\AI\projects\testJava\OA-auth -b refactor/auth refactor/thinkphp-main
git worktree add F:\AI\projects\testJava\OA-user -b refactor/user refactor/thinkphp-main
git worktree add F:\AI\projects\testJava\OA-workflow -b refactor/workflow refactor/thinkphp-main
git worktree add F:\AI\projects\testJava\OA-db -b refactor/db refactor/thinkphp-main
git worktree add F:\AI\projects\testJava\OA-api -b refactor/api refactor/thinkphp-main
git worktree add F:\AI\projects\testJava\OA-frontend -b refactor/frontend refactor/thinkphp-main
git worktree add F:\AI\projects\testJava\OA-test -b refactor/test refactor/thinkphp-main
git worktree add F:\AI\projects\testJava\OA-docs -b refactor/docs refactor/thinkphp-main
git worktree list
```
