# Events in State Machine

## Overview
Events are the triggers that cause state transitions in the state machine. They represent actions or occurrences that can change the state of an item in the system. This guide provides in-depth information about events, their types, and how to work with them effectively.

## Event Types

### 1. Manual Events
Manual events are triggered explicitly by user actions or system code.

**Key Characteristics:**
- Triggered via API calls or user interface actions
- Require explicit invocation
- Can be subject to permissions and validations

**Example Configuration:**
```json
{
  "name": "ship_order",
  "command": "App\\Commands\\ShipOrderCommand",
  "manual": true
}
```

### 2. On-Enter Events
On-enter events are triggered automatically when a state is entered.

**Key Characteristics:**
- Automatically executed when entering a state
- Useful for notifications or automatic actions
- Cannot be triggered manually

**Example Configuration:**
```json
{
  "name": "send_welcome_email",
  "command": "App\\Commands\\SendWelcomeEmailCommand",
  "onEnter": true
}
```

### 3. Timeout Events
Timeout events are triggered after a specified duration in a state.

**Key Characteristics:**
- Automatically triggered after a defined period
- Useful for time-based workflows
- Can be used for reminders or escalations

**Example Configuration:**
```json
{
  "name": "payment_reminder",
  "command": "App\\Commands\\SendPaymentReminderCommand",
  "timeout": "48 hours"
}
```

## Event Properties

### Common Properties
- **name**: Unique identifier for the event
- **command**: (Optional) Class that handles the event logic
- **manual**: Boolean indicating if this is a manual event
- **onEnter**: Boolean indicating if this is an on-enter event
- **timeout**: Duration string for timeout events (e.g., "24 hours", "5 minutes")

## Event Handlers

### Command Classes
Event handlers are PHP classes that implement the `CommandInterface`. They contain the business logic that executes when an event is triggered.

**Example Command Class:**
```php
<?php

namespace App\Commands;

use Tlab\StateMachine\Commands\CommandInterface;

class ShipOrderCommand implements CommandInterface
{
    public function execute(array $eventData): void
    {
        // Business logic to ship the order
        $orderId = $eventData['order_id'];
        // ... shipping logic ...
    }
}
```

## Event Availability

### Conditional Events
Events can be made conditionally available based on:
- Current state of the item
- Item properties
- System flags
- User permissions

**Example with Conditions:**
```json
{
  "name": "approve_order",
  "command": "App\\Commands\\ApproveOrderCommand",
  "manual": true,
  "condition": "App\\Conditions\\IsOrderApprovalRequired"
}
```

## Best Practices

### Naming Conventions
- Use present tense for event names (e.g., `approve_order` not `order_approved`)
- Be specific about the action (e.g., `request_payment` vs `process_payment`)
- Use snake_case for consistency

### Error Handling
- Always implement proper error handling in command classes
- Use custom exceptions for business rule validations
- Log important events and errors

### Performance Considerations
- Keep command classes lightweight
- Move heavy processing to background jobs when possible
- Cache frequently accessed data

## Common Patterns

### Event Chaining
Chain multiple events together by triggering subsequent events in command classes.

### Event Broadcasting
Broadcast events to external systems using a message queue or webhooks.

### Audit Trail
Log all state changes and events for auditing purposes.

## Example: Complete Event Configuration

```json
{
  "events": [
    {
      "name": "place_order",
      "command": "App\\Commands\\PlaceOrderCommand",
      "manual": true,
      "requiredFlags": ["customer_verified"]
    },
    {
      "name": "process_payment",
      "command": "App\\Commands\\ProcessPaymentCommand",
      "manual": true,
      "condition": "App\\Conditions\\IsPaymentMethodValid"
    },
    {
      "name": "send_order_confirmation",
      "command": "App\\Commands\\SendOrderConfirmationCommand",
      "onEnter": true
    },
    {
      "name": "payment_reminder",
      "command": "App\\Commands\\SendPaymentReminderCommand",
      "timeout": "24 hours"
    }
  ]
}
```

## Troubleshooting

### Common Issues
1. **Event Not Available**
   - Check if the current state has a transition for this event
   - Verify any conditions or required flags
   - Check user permissions

2. **Event Not Triggering**
   - Verify the event name matches exactly
   - Check for exceptions in the command class
   - Verify the state machine configuration

3. **Performance Issues**
   - Check for N+1 queries in command classes
   - Consider lazy loading of related data
   - Use caching where appropriate
