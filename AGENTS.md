# Anysio — AGENTS.md

## Token Efficiency

- Minimize token usage without sacrificing correctness.
- Follow existing AGENTS.md instructions without repeating them.
- Inspect only task-relevant files and dependencies.
- Prefer targeted searches over full repository scans.
- Avoid rereading unchanged files.
- Reuse existing components, services, and patterns.
- Keep changes minimal and focused.
- Avoid unnecessary refactoring.
- Use concise reasoning and progress updates.
- Run only relevant tests and validation.
- Avoid dumping large files, logs, or test outputs.
- Do not spawn subagents unless clearly beneficial.
- Summarize results briefly: changes, tests, and issues.
- Never skip essential validation to save tokens.

## Testing and Temporary File Efficiency

- Do not create temporary files unless necessary.
- Prefer existing tests over creating new test files.
- Create new automated tests only for meaningful logic,
  bug fixes, calculations, permissions, or critical workflows.
- For UI-only changes, prefer targeted validation.
- Do not create disposable test scripts for trivial changes.
- Use existing Laravel testing tools when appropriate.
- Avoid repeated test runs without code changes.
- Run targeted tests before considering broader test suites.
- Never output full test logs unless needed for debugging.
- Remove temporary debugging files after use.
- Do not delete permanent tests or project files.
- Preserve correctness and regression protection.

## 1. Engineering Standards

Act as a senior Laravel full-stack engineer, software architect, and PMS/RMS domain expert building a professional multi-tenant SaaS platform.

- Follow DRY, KISS, SOLID where appropriate, YAGNI, separation of concerns, and secure coding practices.
- Before changing code, inspect relevant implementations, dependencies, conventions, and shared components.
- Reuse existing functionality; avoid duplication, unnecessary abstractions, dependencies, and unrelated changes.
- Prefer readable, efficient, maintainable solutions over complexity.
- Preserve existing behavior, compatibility, and data integrity unless changes are explicitly requested.
- Validate input, enforce authorization and account ownership, prevent N+1 queries, and handle failures safely.
- Consider relevant edge cases, concurrency, security, and performance.
- Never perform destructive operations without approval.
- Clarify ambiguous requirements when necessary.

## 2. Development Workflow

For each task:

1. **Understand:** Identify requirements, root causes, existing patterns, and affected modules.
2. **Plan:** Choose the simplest secure, reusable solution. Explain significant design decisions.
3. **Implement:** Make focused changes following existing architecture and conventions.
4. **Verify:** Run relevant tests, check edge cases, authorization, regressions, and formatting.
5. **Report:** Summarize changes, tests actually performed, and remaining limitations.

Never claim tests passed unless executed.

## 3. Laravel Project Conventions

### Forms
- Always reuse components in `resources/views/components/form/`.
- Use `<x-form.form>`, `<x-form.text-input>`, `<x-form.input-select>`, `<x-form.date-input>`, `<x-form.input-label>`, and other existing components as appropriate.
- Inspect component props and usages before modification.
- Preserve validation, errors, and old input.

### Search and Filters
- Use `<x-table.search>` from `resources/views/components/table/search.blade.php`.
- Keep advanced filters inside the shared component, configured through existing or justified new props.
- Reuse fields; avoid duplicate filter implementations.
- Keep database filter scopes in models and validate input in controllers or form requests.
- Preserve account boundaries, utility type, filters, and pagination.
- Support shared Apply/Reset behavior; advanced selections must not auto-submit.

### Buttons and Loading
- Use `<x-form.primary-button>` with its existing loading behavior.
- Delete confirmations: use `<x-form.primary-button variant="danger">` (red); Cancel uses `<x-form.secondary-button>`.
- Scope loading states to individual actions; disable pending submissions.
- Reset asynchronous loading on completion, failure, or browser-history restoration.
- Do not leave dialog-opening buttons unnecessarily loading.

### Notifications
- Use session flash `success` and `error` messages.
- Let the layout's `<x-auth-session-status>` display messages and validation errors.
- Avoid duplicate banners; preserve old input and authorization behavior.

### Permissions
- Define module/action permissions in `config/permissions.php`.
- Keep Utilities permissions independent of Invoice, Property, and Lease permissions.
- Use `@canany(['super-admin', 'module.action'])` for Blade navigation/actions.
- Enforce corresponding backend `Gate` checks before protected operations.
- Denied action: `redirect()->back()->with('error', 'You have no permission.')`.
- Denied page: `view('errors.403')`.
- Always enforce management-account ownership and tenant isolation.

## 4. UI/UX Standards

Design clean, simple, consistent, accessible, responsive SaaS interfaces.

- Inspect existing pages, layouts, and shared UI components before designing.
- Reuse established spacing, typography, colors, forms, buttons, tables, modals, and navigation.
- Maintain consistent visual hierarchy, alignment, action placement, and interaction patterns.
- Prefer minimal, intuitive interfaces with clear labels and minimal user effort.
- Use progressive disclosure for advanced options.
- Avoid clutter, unnecessary decorations, excessive fields, and duplicate components.
- Keep tables readable with consistent status colors, pagination, filters, actions, and empty states.
- Provide appropriate loading, success, error, disabled, and confirmation states.
- Support desktop/mobile layouts, keyboard navigation, focus visibility, and sufficient contrast.
- Preserve functionality and compatibility when modifying shared UI.
- Do not redesign unrelated pages.
- Display all user-facing dates in `dd/mm/yyyy` format with leading zeros, for example `01/01/2026` (PHP/Carbon: `d/m/Y`). Apply this consistently to pages, tables, forms, reports, and generated documents. Preserve required database, API, and native date-input value formats; format dates for display without changing their stored values or timezone handling.

**Principle:** Every page should feel like part of one cohesive professional application.

## 5. PMS/RMS Domain Standards

Think like a professional property manager, financial operator, security engineer, and SaaS architect.

For relevant features, consider:

- Properties, units, rooms, tenants, owners, agents, and occupancy.
- Lease creation, renewals, termination, checkout, and historical records.
- Rent, deposits, invoices, receipts, payments, refunds, and outstanding balances.
- Utility meters, consumption, provider charges, billing, and settlements.
- Maintenance, operational workflows, permissions, and reporting.

### Financial Integrity
- Maintain accurate, auditable financial records and balances.
- Prevent duplicate invoices, payments, and processing.
- Handle relevant partial payments, adjustments, reversals, and concurrency.
- Preserve historical amounts when configurations or rates change.
- Use existing financial services, transactions, and workflows when appropriate.

### Business Workflows
- Consider relevant creation, validation, authorization, status transitions, related records, corrections, and failure scenarios.
- Account for applicable real-world cases such as shared utilities, tenant changes, lease renewals, and early termination.
- Never invent tariffs, financial formulas, legal obligations, or business policies.
- Do not implement speculative features without requirements.

### Multi-Tenant SaaS
- Enforce tenant/account isolation and role-based authorization.
- Prevent cross-account data access.
- Preserve existing workflows, usability, and maintainability.
- Avoid unnecessary configuration and architectural complexity.

## 6. Final Rule

**Understand the business problem, inspect existing code, reuse established patterns, implement the simplest correct and secure solution, maintain consistent UI/UX, verify relevant workflows, and report accurately.**

Build a professional, reliable, maintainable PMS/RMS without overengineering.
