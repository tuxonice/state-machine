# State Machine Core Concepts

## 1. States

### Definition
States represent the various stages that an entity (like an order, document, or process) can be in during its lifecycle.

### Key Characteristics
- **Unique Identification**: Each state has a unique name (e.g., `new`, `paid`, `shipped`)
- **Terminal States**: Some states may be endpoints with no outgoing transitions
- **Initial State**: The starting point of the state machine
- **Human-Readable**: State names should clearly describe the entity's condition

### Example
```json
{
  "states": [
    { "name": "new" },
    { "name": "paid" },
    { "name": "shipped" },
    { "name": "delivered" },
    { "name": "cancelled" }
  ]
}
```

## 2. Transitions

### Definition
Transitions define the possible movements between states and the rules that govern these movements.

### Key Components
- **Source State**: The starting state of the transition
- **Target State**: The destination state
- **Event**: What triggers the transition
- **Condition**: Optional guard clause that must be true for the transition to occur

### Example
```json
{
  "transitions": [
    {
      "source": "new",
      "target": "paid",
      "event": "process_payment",
      "condition": "App\\Conditions\\PaymentIsValid"
    },
    {
      "source": "paid",
      "target": "shipped",
      "event": "ship_order"
    }
  ]
}
```

## 3. Events

### Definition
Events are the triggers that initiate state transitions. They represent something that happens in the system that might cause a state change.

### Key Characteristics
- **Named Actions**: Each event has a unique identifier
- **Optional Commands**: Can be associated with business logic that executes during the transition
- **Event Types**:
  - **OnEnter Events**: Set `onEnter: true` to trigger automatically when entering a state
  - **Timeout Events**: Include a `timeout` field to trigger after a specified duration
  - **Manual Events**: Set `manual: true` for events that require explicit triggering (default behavior)

### Example
```json
{
  "events": [
    {
      "name": "process_payment",
      "command": "App\\Commands\\ProcessPaymentCommand",
      "manual": true
    },
    {
      "name": "ship_order",
      "command": "App\\Commands\\ShipOrderCommand",
      "manual": true
    },
    {
      "name": "payment_timeout",
      "command": "App\\Commands\\HandlePaymentTimeoutCommand",
      "timeout": "24 hours"
    },
    {
      "name": "welcome_email",
      "command": "App\\Commands\\SendWelcomeEmailCommand",
      "onEnter": true
    }
  ]
}
```

## How They Work Together

1. The system starts in an initial state (e.g., `new`)
2. An event is triggered (e.g., `process_payment`)
3. The system checks for a valid transition from the current state using this event
4. If a transition exists and any conditions are met, the state changes to the target state
5. Any associated command is executed during the transition

## Best Practices

### States
- Keep state names simple and descriptive
- Avoid too many states (consider sub-states if needed)
- Document what each state means in your business context

### Transitions
- Ensure all possible state changes are explicitly defined
- Use conditions to enforce business rules
- Keep transition logic simple; move complex logic to commands

### Events
- Use clear, action-oriented names (e.g., `order_placed` not `new_order`)
- Keep event handlers small and focused
- Consider idempotency for events that might be retried

## Example: Complete State Machine

```json
{
  "name": "Order Processing",
  "states": [
    { "name": "new" },
    { "name": "paid" },
    { "name": "shipped" },
    { "name": "delivered" },
    { "name": "cancelled" }
  ],
  "transitions": [
    {
      "source": "new",
      "target": "paid",
      "event": "process_payment",
      "condition": "App\\Conditions\\PaymentIsValid"
    },
    {
      "source": "paid",
      "target": "shipped",
      "event": "ship_order"
    },
    {
      "source": "shipped",
      "target": "delivered",
      "event": "confirm_delivery"
    },
    {
      "source": ["new", "paid"],
      "target": "cancelled",
      "event": "cancel_order"
    }
  ],
  "events": [
    {
      "name": "process_payment",
      "command": "App\\Commands\\ProcessPaymentCommand",
      "manual": true
    },
    {
      "name": "ship_order",
      "command": "App\\Commands\\ShipOrderCommand",
      "manual": true
    },
    {
      "name": "confirm_delivery",
      "command": "App\\Commands\\ConfirmDeliveryCommand",
      "manual": true
    },
    {
      "name": "cancel_order",
      "command": "App\\Commands\\CancelOrderCommand",
      "manual": true
    },
    {
      "name": "payment_timeout",
      "command": "App\\Commands\\HandlePaymentTimeoutCommand",
      "timeout": "24 hours"
    },
    {
      "name": "welcome_email",
      "command": "App\\Commands\\SendWelcomeEmailCommand",
      "onEnter": true
    }
  ]
}
```
