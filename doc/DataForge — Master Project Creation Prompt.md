# DataForge — Master Project Creation Prompt

## 1. Project Overview

Create a production-ready but intentionally simple data integration service named:

**DataForge**

Repository name:

```text
data-forge
```

Purpose:

```text
MySQL
   ↓
Read / Transform Data
   ↓
Split into Groups
   ↓
Send to MOPH API
   ↓
Track Progress
   ↓
Auto Retry / Reconnect / Resume
```

The application will run continuously for **24/7 operation**.

The project must prioritize:

- Simple architecture
- Easy maintenance
- Easy deployment
- Automatic recovery
- Reliable batch processing
- Clear Console UI
- Simple Web UI for configuration
- No unnecessary enterprise architecture

Do NOT over-engineer the project.

---

# 2. IMPORTANT: Existing PHP Reference Code

Before writing any implementation code, inspect the existing PHP reference files.

The repository contains:

```text
mysql-demo/
moph-api-demo/
```

These folders contain the original PHP implementation.

## mysql-demo

Study this folder carefully to understand:

- MySQL connection
- SQL queries
- Database structure
- Data retrieval
- Existing grouping logic
- Existing data transformation
- Existing business rules
- Existing error handling
- Existing authentication/configuration
- Any special field mapping

The Node.js implementation must preserve the existing business logic unless there is a clear reason to improve it.

Do NOT blindly translate PHP syntax into TypeScript.

Understand what the PHP code is doing first.

---

# 3. MOPH API Reference

Study:

```text
moph-api-demo/
```

Understand:

- API endpoint
- HTTP method
- Request headers
- Authentication
- Request payload
- JSON structure
- Required fields
- Response format
- Success conditions
- Error conditions
- HTTP status handling
- API-specific retry requirements
- Any signing/encryption/token logic

The Node.js implementation must reproduce the behavior of the PHP API client correctly.

If the PHP implementation contains secrets, tokens, passwords, or private credentials:

- Do NOT copy them into source code.
- Move them to environment/configuration.
- Never commit secrets to Git.

---

# 4. Technology Stack

Use:

```text
Node.js
TypeScript
MySQL
Axios
Express
PM2
```

Recommended supporting libraries may be used when they simplify implementation.

Prefer lightweight libraries.

Do NOT use:

- NestJS
- React
- Next.js
- Redis
- Kafka
- RabbitMQ
- Kubernetes
- Microservices
- Docker unless it clearly simplifies deployment

The application should remain a single simple service.

---

# 5. Architecture

Use a simple architecture:

```text
                 ┌─────────────────────┐
                 │     Web Browser      │
                 │                     │
                 │ DB Configuration    │
                 │ API Configuration   │
                 │ Status / Monitoring │
                 └──────────┬──────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────┐
│                 DataForge                       │
│                                                 │
│  Express Web Server                             │
│                                                 │
│  ┌──────────────┐                               │
│  │ Config       │                               │
│  └──────────────┘                               │
│                                                 │
│  ┌──────────────┐                               │
│  │ Batch Worker │                               │
│  └──────┬───────┘                               │
│         │                                       │
│         ▼                                       │
│  ┌──────────────┐                               │
│  │ MySQL Client │                               │
│  └──────┬───────┘                               │
│         │                                       │
│         ▼                                       │
│  ┌──────────────┐                               │
│  │ Group Queue  │                               │
│  └──────┬───────┘                               │
│         │                                       │
│         ▼                                       │
│  ┌──────────────┐                               │
│  │ MOPH API     │                               │
│  └──────────────┘                               │
│                                                 │
│  Retry / Resume / Recovery / Logging            │
└─────────────────────────────────────────────────┘
```

Keep this as a single application/process.

---

# 6. Group Processing

The initial implementation must support:

```text
2 Groups
```

The group definitions and grouping logic must be derived from:

```text
mysql-demo/
```

Do not hardcode architecture around exactly two groups.

The design must make it easy to change:

```text
2 groups
→ 3 groups
→ 5 groups
→ 10 groups
→ 15 groups
```

without rewriting the worker.

Use configuration-driven group definitions.

For example:

```env
GROUP_COUNT=2
```

or an equivalent configuration structure.

The exact implementation must follow the business logic discovered from `mysql-demo`.

---

# 7. Batch Processing

The worker should process data in batches/groups.

Conceptually:

```text
MySQL
  ↓
Fetch records
  ↓
Create groups
  ↓
Queue groups
  ↓
Process groups
  ↓
Send API
  ↓
Mark success
```

The implementation must avoid loading unnecessarily large amounts of data into memory.

Prefer pagination/chunking when appropriate.

If the PHP implementation already has an efficient query strategy, preserve it.

---

# 8. Concurrency

Use a standard, lightweight concurrency model.

Default:

```text
CONCURRENCY=2
```

Meaning:

```text
Group 01 ───────→ API
Group 02 ───────→ API

Group 03 waits
Group 04 waits
...
```

When one group finishes, the next queued group can start.

Do not create unlimited concurrent requests.

Concurrency must be configurable.

Example:

```env
CONCURRENCY=2
```

The implementation should allow:

```text
CONCURRENCY=1
CONCURRENCY=2
CONCURRENCY=4
```

without code modification.

Do not use an external queue system.

Use an in-process queue suitable for this application's scale.

---

# 9. Retry Strategy

Implement standard production retry behavior.

Retry transient failures such as:

```text
Network error
ECONNRESET
ETIMEDOUT
ECONNREFUSED
HTTP 408
HTTP 429
HTTP 500
HTTP 502
HTTP 503
HTTP 504
```

Do NOT blindly retry permanent client errors such as:

```text
400
401
403
404
422
```

unless the PHP API implementation indicates otherwise.

Use:

```text
MAX_RETRIES=3
```

Use exponential backoff with jitter.

Example concept:

```text
Attempt 1
   ↓
wait ~1 sec

Attempt 2
   ↓
wait ~2 sec

Attempt 3
   ↓
wait ~4 sec
```

Add small random jitter to avoid synchronized retries.

Do not implement infinite retry loops.

After maximum retries:

```text
Group → FAILED
```

The failure must be recorded so that it can be resumed.

---

# 10. MySQL Auto Reconnect

The system must automatically recover from temporary MySQL connection failures.

Examples:

```text
MySQL server restart
Network interruption
Connection timeout
Connection reset
Too many connections
```

Use a MySQL connection pool.

Recommended:

```text
mysql2/promise
```

The application should:

1. Detect connection failure.
2. Close/discard invalid connection.
3. Reconnect automatically.
4. Retry the database operation when appropriate.
5. Continue processing.

Do not crash the entire application because of a temporary database connection failure.

Use controlled retry/backoff.

---

# 11. API Auto Recovery

If the MOPH API becomes temporarily unavailable:

```text
API unavailable
      ↓
Retry
      ↓
Backoff
      ↓
Retry
      ↓
Still unavailable
      ↓
Mark current work as pending/failed
      ↓
Worker continues safely
```

Do not lose data.

Do not mark a group as successful until the API confirms success.

---

# 12. Resume

Resume is a core requirement.

The system must know the state of each group.

Minimum states:

```text
PENDING
PROCESSING
SUCCESS
FAILED
```

Recommended:

```text
PENDING
PROCESSING
RETRYING
SUCCESS
FAILED
```

If the application crashes:

```text
Group 01 SUCCESS
Group 02 SUCCESS
Group 03 PROCESSING
Group 04 PENDING
```

After restart:

```text
Group 01 → skip
Group 02 → skip
Group 03 → recover/retry
Group 04 → continue
```

A group that was marked SUCCESS must never be sent again automatically.

A PROCESSING group from a previous crashed session must be detected and safely recovered.

Use a persistent local state mechanism.

Do NOT use Redis.

A simple local state database/file is acceptable.

Prefer SQLite if a persistent local state store is required.

The state store must be lightweight and reliable.

---

# 13. Idempotency

Prevent duplicate API submissions whenever possible.

Study the PHP implementation and API behavior to determine whether the API provides:

- transaction ID
- record ID
- request ID
- reference number
- idempotency key

If an idempotency mechanism exists, use it.

If not, create a deterministic request/group identifier where appropriate.

The system must be designed so that restarting DataForge does not unnecessarily duplicate successful API submissions.

---

# 14. Exception Handling

The application must never silently stop.

Every unexpected exception must be:

1. Logged.
2. Classified.
3. Recovered when possible.
4. Retried when appropriate.
5. Resumed when possible.

Example:

```text
Unexpected exception
        ↓
Write log
        ↓
Determine recoverable?
        ↓
YES ──→ reconnect/retry/resume
        │
NO
        ↓
mark failed
        ↓
continue worker
```

Only terminate the process for truly unrecoverable startup/configuration errors.

---

# 15. 24/7 Operation

The application will run continuously.

Do not design it as a one-shot script.

The worker should:

```text
START
  ↓
Connect MySQL
  ↓
Load configuration
  ↓
Start worker
  ↓
Process groups
  ↓
Wait
  ↓
Check for new work
  ↓
Process again
  ↓
Continue forever
```

The exact polling behavior should be determined from the PHP application's business logic.

Make polling interval configurable.

Example:

```env
POLL_INTERVAL=30000
```

Do not create a busy loop.

Use timers/scheduled execution.

---

# 16. Process Manager / Deployment

Use:

**PM2**

The production process should run under PM2.

Example concept:

```bash
npm install
npm run build
pm2 start ecosystem.config.js
pm2 save
pm2 startup
```

The project must provide simple deployment instructions.

PM2 should automatically restart DataForge after:

- application crash
- unexpected exception
- server reboot

But application-level recovery must still exist.

Do NOT rely only on PM2 for recovery.

We need both:

```text
Application Recovery
+
PM2 Process Recovery
```

---

# 17. Configuration Web UI

Create a lightweight Web UI.

No React.

Use:

```text
Express
HTML
CSS
Vanilla JavaScript
```

The UI must allow configuration of:

## Database

```text
MySQL Host
MySQL Port
Database
Username
Password
```

## API

```text
API Endpoint
API Token / Authentication
Timeout
```

## Worker

```text
Group Count
Concurrency
Retry Count
Polling Interval
```

Provide:

```text
Save
Test Connection
Test API
```

buttons.

---

# 18. Configuration Security

Do not expose database passwords or API tokens in URLs.

Do not print secrets in logs.

Mask secrets in UI.

Example:

```text
Password
************
```

The Web UI should not display the raw password after saving.

Use `.env` / configuration storage appropriately.

Provide:

```text
.env.example
```

Never commit:

```text
.env
```

to Git.

---

# 19. Web UI Dashboard

Create a simple dashboard.

Example:

```text
┌───────────────────────────────────────────────┐
│ DATAFORGE                                     │
│ MySQL → MOPH API                              │
├───────────────────────────────────────────────┤
│                                               │
│ MySQL        ● Connected                      │
│ MOPH API     ● Connected                      │
│ Worker       ● Running                        │
│                                               │
│ Records      10,000                           │
│ Groups       2                                │
│                                               │
│ Progress                                      │
│ ███████████████░░░░░ 75%                      │
│                                               │
├───────────────────────────────────────────────┤
│ Groups                                        │
│                                               │
│ Group 01     SUCCESS     5,000                │
│ Group 02     PROCESSING  2,500                │
│                                               │
├───────────────────────────────────────────────┤
│ Statistics                                    │
│                                               │
│ Success      7,500                            │
│ Failed       0                                │
│ Pending      2,500                            │
│ Retry        0                                │
│                                               │
│ Last activity: 18:32:21                       │
└───────────────────────────────────────────────┘
```

The UI should update automatically.

Use simple polling or Server-Sent Events if appropriate.

Do not introduce WebSocket infrastructure unless necessary.

---

# 20. Console UI

The application must also provide a useful console interface.

Example:

```text
╔══════════════════════════════════════════════╗
║                  DATAFORGE                   ║
║             MySQL → MOPH API                ║
╚══════════════════════════════════════════════╝

MySQL       ● Connected
MOPH API    ● Connected
Worker      ● Running

Groups

[01] ████████████████████ SUCCESS
[02] ███████████░░░░░░░░░ PROCESSING

Progress    75%
Success     7,500
Failed      0
Pending     2,500
Retry       0

Last Activity: 18:32:21
```

Console output should remain readable.

Do not print thousands of individual records.

Use summarized progress.

---

# 21. Logging

Implement structured logging.

At minimum:

```text
logs/
```

Log important events:

```text
Application started
MySQL connected
API connected
Worker started
Group started
Group success
Group failed
Retry
Reconnect
Resume
Unexpected exception
Application shutdown
```

Never log:

```text
Database password
API token
Sensitive credentials
```

Use log rotation so logs do not grow forever.

---

# 22. Graceful Shutdown

Handle:

```text
SIGINT
SIGTERM
```

When shutdown occurs:

```text
Stop accepting new work
        ↓
Finish current safe operation
        ↓
Persist state
        ↓
Close MySQL pool
        ↓
Close HTTP server
        ↓
Exit
```

Do not corrupt processing state.

---

# 23. Health Check

Provide:

```text
GET /health
```

Example:

```json
{
  "status": "ok",
  "mysql": "connected",
  "worker": "running"
}
```

Also provide:

```text
GET /api/status
```

for dashboard data.

---

# 24. Project Structure

Keep the project simple.

Recommended structure:

```text
data-forge/
│
├── mysql-demo/
│   └── existing PHP reference
│
├── moph-api-demo/
│   └── existing PHP reference
│
├── src/
│   ├── config/
│   │   └── config.ts
│   │
│   ├── database/
│   │   └── mysql.ts
│   │
│   ├── api/
│   │   └── moph.ts
│   │
│   ├── worker/
│   │   ├── worker.ts
│   │   ├── batch.ts
│   │   └── queue.ts
│   │
│   ├── recovery/
│   │   ├── retry.ts
│   │   └── resume.ts
│   │
│   ├── web/
│   │   ├── server.ts
│   │   └── public/
│   │
│   ├── logger/
│   │   └── logger.ts
│   │
│   └── index.ts
│
├── state/
│
├── logs/
│
├── .env.example
├── .gitignore
├── ecosystem.config.js
├── package.json
├── tsconfig.json
└── README.md
```

Adjust the structure if a simpler structure is better.

Do not create unnecessary folders.

---

# 25. TypeScript Rules

Use strict TypeScript.

Enable:

```json
{
  "compilerOptions": {
    "strict": true
  }
}
```

Avoid:

```typescript
any
```

unless absolutely necessary.

Create types/interfaces for:

```text
Configuration
Group
Record
API Request
API Response
Worker State
Job Status
```

---

# 26. Environment Configuration

Create:

```text
.env.example
```

Example:

```env
NODE_ENV=production

PORT=3000

MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_DATABASE=
MYSQL_USER=
MYSQL_PASSWORD=

MOPH_API_URL=
MOPH_API_TOKEN=

GROUP_COUNT=2
CONCURRENCY=2

MAX_RETRIES=3
REQUEST_TIMEOUT=30000

POLL_INTERVAL=30000
```

Use the actual configuration requirements discovered from the PHP files.

Do not invent API fields when the PHP reference already defines them.

---

# 27. Deployment Goal

Deployment must be extremely simple.

Target workflow:

```bash
git clone <repository>

cd data-forge

npm install

cp .env.example .env

npm run build

pm2 start ecosystem.config.js

pm2 save
```

Provide a complete README explaining:

1. Install Node.js
2. Install dependencies
3. Configure `.env`
4. Test MySQL
5. Test API
6. Build
7. Start with PM2
8. Configure auto-start
9. Check logs
10. Open Web UI
11. Stop/restart
12. Upgrade application

---

# 28. Scripts

Provide simple npm scripts:

```json
{
  "scripts": {
    "dev": "...",
    "build": "...",
    "start": "...",
    "test": "...",
    "lint": "..."
  }
}
```

Development:

```bash
npm run dev
```

Production:

```bash
npm run build
npm start
```

PM2 should run the compiled production application.

---

# 29. Testing

Add basic tests for the most important logic.

At minimum test:

- Group calculation
- Retry decision
- Retry backoff
- Successful API response
- API failure
- Resume state
- Failed group recovery
- Configuration validation

Do not build an enormous test suite.

Focus on critical reliability logic.

---

# 30. Configuration Validation

At startup validate:

```text
MYSQL_HOST
MYSQL_DATABASE
MYSQL_USER
MYSQL_PASSWORD
MOPH_API_URL
```

If required configuration is missing:

```text
ERROR: Missing configuration: MOPH_API_URL
```

Exit cleanly.

Do not start the worker with invalid configuration.

---

# 31. Security

Implement basic production security:

- Do not expose secrets.
- Do not log secrets.
- Validate configuration.
- Validate API responses.
- Sanitize Web UI input.
- Avoid SQL injection.
- Use parameterized SQL.
- Do not use dynamic SQL with raw user input.
- Add basic authentication to configuration UI if appropriate.
- Do not expose configuration endpoints publicly without protection.

If the application is intended to run only on an internal server, keep the security implementation simple but safe.

---

# 32. Important Implementation Rule

Before implementing:

## STEP 1

Inspect:

```text
mysql-demo/
```

## STEP 2

Inspect:

```text
moph-api-demo/
```

## STEP 3

Document what the PHP implementation actually does.

## STEP 4

Map the PHP behavior to TypeScript.

Example:

```text
PHP
 ↓
MySQL query
 ↓
Group calculation
 ↓
Data transformation
 ↓
API request
 ↓
Response handling
```

becomes:

```text
TypeScript
 ↓
MySQL repository
 ↓
Batch processor
 ↓
Transformer
 ↓
MOPH API client
 ↓
Response handler
```

## STEP 5

Only after understanding the PHP code, start implementation.

---

# 33. Do Not Guess

If the PHP source contains unclear business logic:

Do NOT guess.

Instead:

1. Identify the unclear behavior.
2. Search other files in the repository for references.
3. Preserve existing behavior where possible.
4. Clearly mark TODO if implementation cannot be determined.

The PHP reference code is the source of truth for business behavior.

---

# 34. Development Strategy

Implement in phases.

## Phase 1

Create project foundation:

```text
Node.js
TypeScript
Express
MySQL
Axios
configuration
logging
```

## Phase 2

Implement MySQL integration based on:

```text
mysql-demo/
```

## Phase 3

Implement MOPH API client based on:

```text
moph-api-demo/
```

## Phase 4

Implement 2-group processing.

## Phase 5

Implement concurrency.

## Phase 6

Implement retry.

## Phase 7

Implement persistent resume state.

## Phase 8

Implement automatic MySQL/API recovery.

## Phase 9

Implement Web UI.

## Phase 10

Implement PM2 deployment.

## Phase 11

Add tests.

## Phase 12

Create README.

---

# 35. Acceptance Criteria

The project is considered complete when:

### MySQL

- [ ] Can connect to MySQL.
- [ ] Can execute the required query.
- [ ] Can reconnect after temporary connection failure.
- [ ] Uses connection pooling.
- [ ] Does not expose credentials.

### API

- [ ] Correctly reproduces PHP API behavior.
- [ ] Sends correct payload.
- [ ] Handles success.
- [ ] Handles transient errors.
- [ ] Handles permanent errors.
- [ ] Retries transient failures.

### Batch

- [ ] Supports 2 groups initially.
- [ ] Group count is configurable.
- [ ] Concurrency is configurable.
- [ ] Does not overload the API.
- [ ] Tracks group status.

### Recovery

- [ ] Automatically reconnects to MySQL.
- [ ] Automatically retries API requests.
- [ ] Automatically resumes after application restart.
- [ ] Detects interrupted PROCESSING groups.
- [ ] Does not resend completed groups unnecessarily.
- [ ] Handles unexpected exceptions safely.

### UI

- [ ] Web UI exists.
- [ ] Can configure MySQL.
- [ ] Can configure API endpoint.
- [ ] Can test MySQL connection.
- [ ] Can test API connection.
- [ ] Displays worker status.
- [ ] Displays group progress.
- [ ] Displays success/failure/pending.
- [ ] Updates without manually refreshing.

### Production

- [ ] Runs 24/7.
- [ ] Works with PM2.
- [ ] Restarts after crash.
- [ ] Starts after server reboot.
- [ ] Has log rotation.
- [ ] Has graceful shutdown.
- [ ] Has health endpoint.
- [ ] Has README deployment instructions.

---

# 36. Final Development Principle

The most important requirement is:

> **Keep DataForge simple, reliable, recoverable, and easy to deploy.**

Do not turn this project into a large enterprise platform.

The target architecture is:

```text
             DATAFORGE

        ┌───────────────┐
        │   Web UI      │
        │ Config/Status │
        └───────┬───────┘
                │
                ▼
┌───────────────────────────────┐
│        Node.js Service        │
│                               │
│ MySQL → Batch → API           │
│                               │
│ Retry → Recovery → Resume     │
└───────────────────────────────┘
                │
                ▼
              PM2

       24/7 Operation
```

Build the simplest solution that satisfies all requirements above.

Do not add infrastructure unless it is clearly necessary.

Before finishing, verify the implementation against the acceptance criteria and provide a concise summary of:

- Files created
- Architecture
- How recovery works
- How resume works
- How to configure
- How to run in development
- How to deploy with PM2
- How to monitor the service