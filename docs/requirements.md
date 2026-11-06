# Requirement Document - Rasoi Express (Food Ordering System)

## 1. Overview
Rasoi Express is a web application where customers browse a restaurant menu, add dishes to a cart, place orders and track them. Admins manage the menu, orders and users and view analytics.

## 2. User roles
| Role | Can do |
|------|--------|
| Visitor | Browse and search the menu, register, log in, reset password |
| Customer | Everything a visitor can, plus cart, checkout, order history, cancel pending orders, edit profile and picture, change password, personal dashboard |
| Admin | Dashboard statistics and charts, CRUD for dishes and categories, update order status, manage users (change role, block, delete) |

## 3. Functional requirements
1. Authentication: register, login, logout, forgot/reset password (token valid 30 minutes).
2. Menu: search by name, filter by category and veg/non-veg, sort by price/name, pagination.
3. Cart: add, change quantity, remove, clear (stored in session).
4. Checkout: delivery address, phone, note, payment (COD/UPI on delivery). Order and items are saved in one DB transaction.
5. Orders: customer list with status filter and pagination, order detail, cancel while pending.
6. Customer dashboard: totals, active orders, amount spent, recent orders, favourite dishes.
7. Profile: edit name, phone, address, upload profile picture, change password.
8. Admin: dashboard (revenue, orders, pending, customers, active users in last 7 days, menu items), chart of orders per day, orders by status, top dishes, CRUD for foods/categories, order status updates, user management.

## 4. Non-functional requirements
- Responsive on mobile, tablet and desktop (Bootstrap 5).
- Security: prepared statements (PDO), `password_hash`, CSRF tokens, output escaping, session ID regeneration on login, role checks on every admin page, upload validation (size, MIME type, image check, random file names), login throttling, blocked-account check.
- Performance: foreign keys and indexes on lookup columns.

## 5. Use cases
| ID | Use case | Actor | Main flow |
|----|----------|-------|-----------|
| UC1 | Register | Visitor | Fill form, server validates, account created |
| UC2 | Log in | Visitor | Email + password, redirected to dashboard (admin goes to admin panel) |
| UC3 | Reset password | Visitor | Enter email, open reset link, set new password |
| UC4 | Order food | Customer | Browse, add to cart, checkout, order saved as "pending" |
| UC5 | Track / cancel order | Customer | Open My orders, view status, cancel if pending |
| UC6 | Update profile | Customer | Edit details, upload picture |
| UC7 | Manage menu | Admin | Add, edit, hide or delete dishes and categories |
| UC8 | Process orders | Admin | Move order through preparing, out for delivery, delivered |
| UC9 | Manage users | Admin | Search, change role, block/unblock, delete |
| UC10 | View analytics | Admin | Dashboard stats and charts |

## 6. Order status flow
`pending` -> `preparing` -> `out_for_delivery` -> `delivered`  (or `cancelled`)

## 7. Wireframes
Sketch these in Figma or Canva for the submission (the built pages follow the same layout):
1. Home / menu: hero banner, filter bar (search, category, veg, sort), 4-column dish grid, pagination.
2. Cart: table with quantity inputs, subtotal, checkout button.
3. Checkout: address form on the left, order summary on the right.
4. Login / Register: single centered card.
5. Customer dashboard: three stat cards, recent orders table, favourites list.
6. Profile: avatar upload and details on the left, change password on the right.
7. Admin dashboard: six stat cards, bar chart (orders per day), doughnut (by status), top dishes and latest orders.
8. Admin tables (foods, orders, users): filter bar, table, pagination.
