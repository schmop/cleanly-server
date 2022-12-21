# POST https://`<your_domain>`/cleanly/v1/webhook

This endpoint will be called when a task was completed in a registered household. You can register a webhook for your household in your household settings.

## Authentication
You will receive the `AuthToken` when registering `<your_domain>` in the household settings.
```
Authorization: Bearer <AuthToken>
```

## Body Data

| key       | type        |
|-----------|-------------|
| action    | "task_done" |
| household | Household   |
| task      | Task        |
| done_by   | User        |

### Household

| key     | type   |
|---------|--------|
| id      | number |
| name    | string |

### Task

| key     | type   |
|---------|--------|
| id      | number |
| name    | string |
| stars   | number |

### User

| key     | type   |
|---------|--------|
| id      | number |
| name    | string |

## Example Request

```json
{
    "action": "task_done",
    "household": {
        "id": 2,
        "name": "Flat"
    },
    "task": {
        "id": 1,
        "name": "Clean fridge",
        "stars": 6
    },
    "done_by": {
        "id": 13,
        "name": "John Doe"
    }
}
```