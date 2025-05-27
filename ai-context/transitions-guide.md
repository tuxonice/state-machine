# Transitions in State Machine

## Overview
Transitions define the allowed movements between states in a state machine. They are the core mechanism by which entities change their state in response to events, conditions, or timeouts. This guide explains transition properties, types, and best practices for implementing robust workflows.

## Transition Structure
A transition connects a source state to a target state and specifies the event and any conditions required for the transition to occur.

**Example Configuration:**
```json
{
  "source": "pending_payment",
  "target": "paid",
  "event": "process_payment",
  "condition": "App\\Conditions\\PaymentIsValid"
}
```

## Transition Properties
- **source**: Name of the state from which the transition starts (required)
- **target**: Name of the state to which the transition leads (required)
- **event**: Name of the event that triggers the transition (required)
- **condition**: (Optional) Class that must evaluate to true for the transition to be allowed

## Transition Types

### 1. Event-Driven Transitions
Triggered by explicit events (manual, onEnter, or timeout events).

**Example:**
```json
{
  "source": "created",
  "target": "processing",
  "event": "start_processing"
}
```

### 2. Conditioned Transitions
Only allowed if a specific condition is met.

**Example:**
```json
{
  "source": "paid",
  "target": "shipped",
  "event": "ship_order",
  "condition": "App\\Conditions\\IsShippingAllowed"
}
```

### 3. Timeout Transitions
Triggered automatically after a specified duration in the source state.

**Example:**
```json
{
  "source": "awaiting_payment",
  "target": "cancelled",
  "event": "payment_timeout",
  "timeout": "48 hours"
}
```

## Transition Lifecycle
1. **Current state** is checked for outgoing transitions.
2. **Event** is triggered (manually, onEnter, or via timeout).
3. **Conditions** are evaluated (if any).
4. **Transition** is executed if allowed:
    - State changes from source to target
    - Any associated command/action is executed
    - Side effects (notifications, logging, etc.) are handled

## Advanced Concepts

### Multiple Transitions for One Event
A single event can trigger different transitions depending on conditions.

**Example:**
```json
[
  {
    "source": "review",
    "target": "approved",
    "event": "complete_review",
    "condition": "App\\Conditions\\IsApproved"
  },
  {
    "source": "review",
    "target": "rejected",
    "event": "complete_review"
  }
]
```

### Transition Actions
Transitions can have their own actions (commands) that override the event's command.

**Example:**
```json
{
  "source": "processing",
  "target": "completed",
  "event": "finish_processing",
  "action": "App\\Commands\\FinalizeProcessingCommand"
}
```

### Transition Priority
When multiple transitions are possible for the same event, the one with the highest priority (lowest number) is chosen.

**Example:**
```json
[
  {
    "source": "pending",
    "target": "approved",
    "event": "review",
    "condition": "App\\Conditions\\IsAutoApproved",
    "priority": 1
  },
  {
    "source": "pending",
    "target": "manual_review",
    "event": "review",
    "priority": 10
  }
]
```

## Best Practices
- Always define clear source and target states
- Use conditions to enforce business rules
- Document the purpose of each transition
- Avoid ambiguous transitions (multiple transitions for the same event and state without clear priorities)
- Handle errors and exceptions in transition actions
- Log all state changes for traceability

## Example: Complete Transition Configuration
```json
[
  {
    "source": "new",
    "target": "processing",
    "event": "start_processing"
  },
  {
    "source": "processing",
    "target": "completed",
    "event": "finish_processing",
    "action": "App\\Commands\\FinalizeProcessingCommand"
  },
  {
    "source": "processing",
    "target": "cancelled",
    "event": "cancel_processing",
    "condition": "App\\Conditions\\CanBeCancelled"
  },
  {
    "source": "processing",
    "target": "timeout",
    "event": "processing_timeout",
    "timeout": "2 hours"
  }
]
```

## Troubleshooting
### Common Issues
1. **Transition not triggered**
   - Check if the event name matches
   - Verify the current state and available transitions
   - Ensure all conditions are met
2. **Multiple transitions for one event**
   - Use priorities to resolve ambiguity
   - Review conditions for exclusivity
3. **Unexpected state changes**
   - Check for side effects in transition actions
   - Log all transitions for audit
