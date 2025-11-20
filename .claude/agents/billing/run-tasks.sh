#!/bin/bash

echo "==================================="
echo "WiFight Billing & Payment Agent"
echo "==================================="

CONFIG_FILE=".claude/agents/billing/agent-config.json"

# Task 1: Stripe Integration
echo "Task 1: Implementing Stripe payment gateway..."
claude-code task \
  --agent="billing-agent" \
  --config="$CONFIG_FILE" \
  --task="billing-001" \
  --output="backend/services/payments/" \
  --prompt="Create comprehensive Stripe payment gateway integration for WiFight:

1. StripeGateway.php:
   - Initialize Stripe with API keys
   - Create customer profiles
   - Process one-time payments
   - Create subscriptions
   - Update subscription plans
   - Cancel subscriptions
   - Process refunds
   - List payment methods
   - Set default payment method

2. Payment flow:
   - Create payment intent
   - Confirm payment with SCA (3D Secure)
   - Handle payment success/failure
   - Store payment metadata (plan_id, user_id)

3. Subscription flow:
   - Create Stripe customer
   - Attach payment method
   - Create subscription with trial period
   - Handle subscription lifecycle events
   - Proration on plan changes

4. Error handling:
   - Card declined
   - Insufficient funds
   - Authentication required
   - Network errors
   - Webhook signature verification

Use stripe/stripe-php library. Include comprehensive logging and PCI-DSS compliant practices."

# Task 2: PayPal Integration
echo "Task 2: Implementing PayPal integration..."
claude-code task \
  --agent="billing-agent" \
  --config="$CONFIG_FILE" \
  --task="billing-002" \
  --output="backend/services/payments/" \
  --prompt="Create PayPal REST API integration for WiFight:

1. PayPalGateway.php:
   - OAuth2 authentication
   - Create orders
   - Capture payments
   - Process refunds
   - Create billing plans
   - Create subscriptions
   - Manage subscription lifecycle

2. Payment flow:
   - Create order with plan details
   - Redirect to PayPal approval
   - Capture order after approval
   - Handle return URLs
   - Verify payment completion

3. Subscription flow:
   - Create billing plan
   - Create billing agreement
   - Handle subscription webhooks
   - Cancel/suspend subscriptions

4. Webhook events:
   - PAYMENT.SALE.COMPLETED
   - BILLING.SUBSCRIPTION.CREATED
   - BILLING.SUBSCRIPTION.CANCELLED
   - Handle IPN (Instant Payment Notification)

Use paypal/rest-api-sdk-php. Include sandbox/production environment switching."

# Task 3: Mobile Money Integration
echo "Task 3: Integrating mobile money payments..."
claude-code task \
  --agent="billing-agent" \
  --config="$CONFIG_FILE" \
  --task="billing-003" \
  --output="backend/services/payments/" \
  --prompt="Create mobile money payment integration for East Africa:

1. MobileMoneyGateway.php - Abstract class:
   - Standard interface for all providers
   - Methods: initiatePayment(), checkStatus(), processCallback()

2. MPesaGateway.php (Safaricom M-Pesa - Kenya):
   - STK Push (Lipa Na M-Pesa)
   - C2B payment confirmation
   - Transaction status query
   - B2C (refunds/withdrawals)
   - OAuth token generation
   - Callback URL handling

3. AirtelMoneyGateway.php:
   - Payment collection API
   - Disbursement API
   - Transaction status
   - Webhook handling

4. MTNMobileMoneyGateway.php:
   - Collection request
   - Status check
   - Callback processing

5. Features:
   - Phone number validation
   - Amount validation (min/max limits)
   - Currency support (KES, UGX, TZS)
   - Transaction reference generation
   - Retry logic for failed transactions
   - Reconciliation support

Include API credentials configuration and testing with sandbox environments."

# Task 4: Subscription Management
echo "Task 4: Creating subscription management system..."
claude-code task \
  --agent="billing-agent" \
  --config="$CONFIG_FILE" \
  --task="billing-004" \
  --context="database/schema/complete-schema.sql" \
  --output="backend/services/billing/" \
  --prompt="Create comprehensive subscription management system for WiFight:

1. SubscriptionManager.php:
   - createSubscription(user_id, plan_id, payment_method)
   - renewSubscription(subscription_id)
   - upgradeSubscription(subscription_id, new_plan_id)
   - downgradeSubscription(subscription_id, new_plan_id)
   - cancelSubscription(subscription_id, reason)
   - suspendSubscription(subscription_id)
   - reactivateSubscription(subscription_id)
   - calculateProration(old_plan, new_plan, days_remaining)

2. Renewal logic:
   - Check expiring subscriptions (cron job)
   - Attempt payment renewal
   - Retry on failure (3 attempts, exponential backoff)
   - Send renewal notifications
   - Apply grace period (3 days)
   - Suspend on payment failure
   - Disconnect user session on suspension

3. Plan changes:
   - Immediate upgrade with prorated charge
   - Downgrade at period end (or immediate with credit)
   - Calculate proration amount
   - Create adjustment invoices

4. Auto-renewal:
   - Check auto_renew flag
   - Process renewals at subscription end_date
   - Handle failed renewals
   - Update subscription dates

5. Lifecycle events:
   - subscription.created
   - subscription.renewed
   - subscription.upgraded
   - subscription.downgraded
   - subscription.cancelled
   - subscription.suspended
   - subscription.expired

Integrate with payment gateways and controller management for session control."

# Task 5: Invoice Generation
echo "Task 5: Implementing invoice generation..."
claude-code task \
  --agent="billing-agent" \
  --config="$CONFIG_FILE" \
  --task="billing-005" \
  --output="backend/services/billing/" \
  --prompt="Create professional invoice generation system:

1. InvoiceGenerator.php:
   - generateInvoice(subscription_id, type='subscription|payment')
   - createPDF(invoice_data)
   - sendInvoiceEmail(invoice_id, user_email)
   - generateInvoiceNumber() // Format: INV-YYYYMMDD-XXXX
   - calculateTax(amount, tax_rate)
   - applyDiscount(amount, discount_code)

2. Invoice types:
   - Subscription invoices (recurring)
   - One-time payment invoices
   - Adjustment invoices (upgrades/downgrades)
   - Credit notes (refunds)

3. Invoice data:
   - Invoice number (unique, sequential)
   - Invoice date
   - Due date
   - Bill from (company details)
   - Bill to (customer details)
   - Line items (plan, quantity, rate, amount)
   - Subtotal
   - Tax (VAT/Sales Tax)
   - Discount
   - Total amount
   - Payment status
   - Payment method

4. PDF generation:
   - Use TCPDF or Dompdf library
   - Professional template
   - Company logo
   - QR code for invoice verification
   - Payment instructions

5. Tax calculation:
   - Support multiple tax rates by country
   - VAT for EU countries
   - Sales tax for US states
   - Tax exempt handling

6. Invoice storage:
   - Save PDF to storage/invoices/
   - Store invoice data in database
   - Generate public invoice URL

Include email templates and invoice preview endpoint."

# Task 6: Webhook Handlers
echo "Task 6: Creating webhook handlers..."
claude-code task \
  --agent="billing-agent" \
  --config="$CONFIG_FILE" \
  --task="billing-006" \
  --context="backend/services/payments/" \
  --output="backend/api/webhooks/" \
  --prompt="Create secure webhook handlers for all payment gateways:

1. stripe-webhook.php:
   - Verify webhook signature (Stripe-Signature header)
   - Handle events:
     * payment_intent.succeeded
     * payment_intent.payment_failed
     * customer.subscription.created
     * customer.subscription.updated
     * customer.subscription.deleted
     * invoice.payment_succeeded
     * invoice.payment_failed
   - Update database on events
   - Trigger notifications
   - Return 200 OK quickly (process async)

2. paypal-webhook.php:
   - Verify webhook signature
   - Handle events:
     * PAYMENT.SALE.COMPLETED
     * BILLING.SUBSCRIPTION.CREATED
     * BILLING.SUBSCRIPTION.CANCELLED
     * BILLING.SUBSCRIPTION.SUSPENDED
   - IPN verification

3. mpesa-webhook.php:
   - STK Push callback
   - C2B confirmation callback
   - Result code handling
   - Transaction verification

4. webhook-processor.php (async):
   - Queue webhook events
   - Process webhooks asynchronously
   - Retry failed processing
   - Log all webhook events

5. Security:
   - Signature verification for all webhooks
   - IP whitelist (optional)
   - Rate limiting
   - Idempotency (prevent duplicate processing)
   - Logging for debugging

6. Database updates:
   - Update payment status
   - Update subscription status
   - Create payment records
   - Log webhook events in audit_logs

Include webhook testing tools and comprehensive logging."

# Task 7: Dunning Management
echo "Task 7: Implementing dunning management..."
claude-code task \
  --agent="billing-agent" \
  --config="$CONFIG_FILE" \
  --task="billing-007" \
  --context="backend/services/billing/SubscriptionManager.php" \
  --output="backend/services/billing/" \
  --prompt="Create intelligent dunning management system for failed payments:

1. DunningManager.php:
   - processFailedPayments() // Cron job
   - retryPayment(subscription_id, attempt_number)
   - sendDunningNotification(user_id, type)
   - applyGracePeriod(subscription_id)
   - suspendAccount(subscription_id)
   - offerPaymentPlanOptions(user_id)

2. Retry schedule:
   - Attempt 1: Immediately after failure
   - Attempt 2: 3 days after failure
   - Attempt 3: 7 days after failure
   - Final: 10 days - suspend account

3. Grace period:
   - 3-day grace period after expiry
   - Service continues during grace period
   - Daily reminder notifications
   - Countdown to suspension

4. Customer communications:
   - Email notification on payment failure
   - SMS notification (optional)
   - In-app notification
   - Update account dashboard with payment status
   - Provide easy payment update flow

5. Dunning email templates:
   - Payment failed - update payment method
   - First reminder - 3 days remaining
   - Second reminder - 1 day remaining
   - Account suspended - reactivate now
   - Account reactivated - thank you

6. Recovery options:
   - One-click payment method update
   - Alternative payment methods
   - Payment plan options (for high-value customers)
   - Downgrade to lower plan
   - Pause subscription (temporary)

7. Analytics:
   - Track dunning success rate
   - Revenue recovery metrics
   - Churn prevention effectiveness
   - Average recovery time

Include configuration for retry schedules and notification preferences."

# Task 8: Financial Reporting
echo "Task 8: Creating financial reporting system..."
claude-code task \
  --agent="billing-agent" \
  --config="$CONFIG_FILE" \
  --task="billing-008" \
  --context="database/schema/complete-schema.sql" \
  --output="backend/api/reports/financial/" \
  --prompt="Create comprehensive financial reporting API endpoints:

1. GET /api/v1/reports/revenue:
   - Total revenue (today, week, month, year, custom range)
   - Revenue by plan
   - Revenue by payment method
   - Revenue trends (chart data)
   - YoY/MoM growth

2. GET /api/v1/reports/mrr:
   - Monthly Recurring Revenue (MRR)
   - New MRR
   - Expansion MRR (upgrades)
   - Contraction MRR (downgrades)
   - Churned MRR
   - Net MRR movement

3. GET /api/v1/reports/arr:
   - Annual Recurring Revenue
   - ARR growth rate

4. GET /api/v1/reports/subscriptions:
   - Active subscriptions count
   - New subscriptions (period)
   - Cancelled subscriptions (period)
   - Churn rate
   - Subscription distribution by plan

5. GET /api/v1/reports/payments:
   - Total payments processed
   - Payment success rate
   - Failed payments count
   - Refunds issued
   - Average transaction value
   - Payment method distribution

6. GET /api/v1/reports/customers:
   - Total customers
   - New customers (period)
   - Customer lifetime value (CLV)
   - Average revenue per user (ARPU)
   - Customer retention rate

7. GET /api/v1/reports/financial-summary:
   - Dashboard summary with key metrics
   - Revenue, MRR, ARR
   - Active subscriptions
   - Payment success rate
   - Quick stats

8. Export functionality:
   - Export to CSV
   - Export to Excel
   - Export to PDF
   - Schedule automated reports (email)

Include data visualization helpers (chart data format) and caching for expensive queries."

echo "==================================="
echo "Billing & Payment Agent tasks completed!"
echo "==================================="