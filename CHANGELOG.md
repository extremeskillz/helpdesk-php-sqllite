# Changelog

## 1.3.0
- Reworked authorization around additive direct-user and group role assignments.
- Added a granular permission catalog for ticket actions, users, groups, roles, settings, reports, categories, and statuses.
- Added group-scoped ticket visibility; `tickets.manage` is now team-scoped for compatibility rather than a blanket all-ticket bypass.
- Protected the System Administrator role from group assignment and permission removal; migration preserves users who previously received administrator access through a group.
- Added direct multi-role assignment to user management and permission-specific administration navigation and route checks.
- Added TinyMCE Community rich-text editing for customer-facing email templates, with an HTML fallback when the CDN is unavailable.
- Added clickable `{{ticket.url}}`, `{ticket.url}`, and legacy `{ticket_url}` placeholders. Links are rendered as absolute URLs using the configured application base URL.
- Added HTML allow-list filtering for saved email template content before sending.

## 1.2.9
- Added guest choice to register or continue without an account, with expanded guest contact details.
- Removed the synthetic Guest Ticket Holder account; guest tickets now use a nullable requester user reference. Existing databases are migrated automatically without discarding ticket history.
- Added user profile editing and matching administrator-editable contact fields.
- Removed application-specific upload-size caps; uploads now follow PHP/server limits, which are displayed in System Information and documented.

## 1.2.8
- Fixed signed-in users being unable to open the new-ticket page.
- Added company logo upload in Settings and logo embedding in customer-facing email templates.
- Added administrator user deletion with ticket, comment, and attachment history preservation.
- Added date-range ticket reports with team filtering and HTML5/PDF exports.

## 1.2.7
- Removed the redundant Ticket Overview panel and made dashboard filters collapsible.
- Added registered-email detection for guest submissions.
- Added configurable remembered OTP verification period.
- Added editable email templates and verification/activity timestamp migrations.

## 1.2.6
- Added configurable email OTP for logins, registrations, and guest submissions.
- Added role and group permission foundations, multiple group memberships, dashboard filters, white-label settings, and software version display.

## 1.2.5
- Previous validated baseline release.
