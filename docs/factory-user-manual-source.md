# Source material: Factory system user manual

## Instructions for the writer (read first)

Turn this source into a clear **user manual** for the staff of a garment factory that uses this system. Rules:

- Readers are **factory staff**: owner, sales staff, production supervisor, store keeper, accountant. They are not technical.
- Use the **navigation paths** given here (for example "Sidebar → Sales → Sales orders → New order"). No screenshots or images.
- Keep the exact names of menus, buttons, statuses and fields as written here in **bold** or "quotes"; they match the screens.
- Write step by step, short sentences, one task per section. Add a short "Good to know" box where a rule is explained.
- Do not mention anything outside this document (no technical terms, no hosting, no developers, no other modules).
- Suggested structure: 1. Getting started, 2. Daily work by role (quick guides), 3. Full chapters per area, 4. Rules the system enforces, 5. Glossary, 6. FAQ.
- Money is shown with the business currency (for example "RWF 15,000"). Document numbers look like "SO-2026-00001".

---

## 1. Getting started

### 1.1 Logging in
- Open the system address given by your administrator and go to the staff login page (address ends with `/app/login`).
- Enter your **email** and **password**, then **Log in**.
- If two-factor authentication is on for your account, enter the 6-digit code from your authenticator app.
- Forgot your password: on the login page choose **Forgot Password?**, enter your email, open the link you receive, and choose a new password.
- Only one person should use an account. Each account sees only what its role allows.

### 1.2 The screen
- **Sidebar (left)**: all areas you are allowed to use, grouped under headings: **Inventory**, **Sales**, **Production**, **Garments**, **Purchasing**, **Reports**, **Billing**, **Management**, **System**. You only see the items your role allows.
- **Top bar (right)**: notifications bell and your name. The menu under your name gives **Profile** and **Log Out**.
- **Dashboard** (first item in the sidebar): cards with today's key numbers (open orders, overdue orders, items in stock, low stock items, open work orders, pieces finished today, quotes to follow up, owed by customers, overdue invoices, items to reorder, purchase orders awaiting goods) and quick actions (**New order**, **New invoice**…). Clicking a card opens the matching list.
- Messages after an action appear at the top (green = done, red = refused, with the reason).
- Lists have a **Filters** bar (search, status, dates…), page numbers at the bottom, and usually **PDF** and **Excel** buttons to export what the list shows.
- Some sensitive actions (cancelling, deleting, reversing a payment, marking workers paid) ask for **your password** in a pop-up before continuing.

### 1.3 Your profile
- Sidebar → System → **Profile**: change your name, email, **Change Password**, and turn on two-factor authentication (recommended for owners and accountants).

### 1.4 Notifications
- The bell (top right) and Sidebar → System → **Notifications** show messages from the system, for example about your subscription.

---

## 2. Management (owner / admin)

### 2.1 Users (staff accounts)
- Sidebar → Management → **Users** → **Add Staff**: name, email, role, password. The person logs in with that email.
- Open a user to change details, change the role, or deactivate the account (an inactive user cannot log in). The last admin of the business cannot be deactivated.

### 2.2 Roles (what each person may do)
- Sidebar → Management → **Roles** → create a role (for example "Sales", "Production supervisor", "Store keeper", "Accountant") and tick its **Permissions**.
- Permissions are grouped by area. In each area the actions are: list, read, create, update, delete, approve. Special meanings:
  - Sales orders: **approve** = confirm and deliver; **delete** = cancel.
  - Quotations: **approve** = convert to order.
  - Work orders: **approve** = close; **delete** = cancel.
  - Invoices: **delete** = cancel with a credit note. Customer payments: **delete** = reverse.
  - Purchase orders: **approve** = mark as ordered; **delete** = cancel. Supplier payments: **delete** = reverse.
  - Workers and pay: **approve** = mark pay as paid.
- The **Admin** role has everything and cannot be edited.

### 2.3 Customers
- Sidebar → Management → **Customers** → **+ Add customer**.
- Fields: **Type** (Company or Person), **Company name** (required for a company), **Name**, **Contact person**, **Tax ID (TIN)**, **Phone**, **Email address** (optional; unique within your business), **Address**, **Delivery address**, **Payment terms (days)** (0 = pays on delivery), **Notes**.
- List: search by name, company, phone, email or TIN; filter by type and status; export PDF/Excel.
- Customer page (click **Manage**): details, status buttons (**Activate**, **Make inactive**, **Ban**), and panels with the customer's **Orders**, **Invoices** (with how much they **Owe**) and **Measurements**. Buttons **+ New order** and **+ New invoice** start a document for that customer.
- **Delete**: only possible for a customer with no orders, quotes or invoices. Otherwise make the customer **Inactive**.
- **Website access** (only if your business has turned customer login on): **Send invitation** emails the customer a link to create a password; **Remove access** removes it.

### 2.4 Settings and audit log
- Sidebar → System → **Settings**: business name, logo, brand colour.
- Sidebar → System → **Audit Logs**: every action in your business (who, what, when). Use it to check what happened to an order, invoice or stock.

---

## 3. Inventory (stock)

### 3.1 Items (products and materials)
- Sidebar → Inventory → **Items**. Tabs: **All**, **Finished goods**, **Materials**.
- Filters: search (name, SKU, category), category, stock (**Low stock**, **Out of stock**), status (**Active**, **Inactive**, All). Export PDF/Excel.
- **+ Add item**:
  1. **Type**: Finished good (what you sell) or Material (fabric, buttons, thread…).
  2. **Name**, **SKU**, **Category**, **Unit** (pcs, m, roll, kg…), **Reorder level** (shown as low stock at or below this), **Description**.
  3. **Sizes and colours**: fill **First option** (e.g. "Size") and **Second option** (e.g. "Colour"); leave empty for an item without sizes.
  4. **Generate all combinations**: type values separated by commas (e.g. "S, M, L, XL" and "White, Blue") and click the button: one row per combination appears.
  5. For each row (variant): **SKU**, **Cost price**, **Sale price**, **Reorder level**, and **Opening stock** (goes into the default store when saved).
  6. **Save**.
- Item page (click the item): stock per size/colour and per store, **Reserved** (promised on confirmed orders) and **Available**, the recent **Stock history**, and the **Change stock** panel.
- **Change stock** panel (right side):
  - **Receive**: stock coming in (delivery, return, found). Optional **Unit cost**.
  - **Issue**: stock going out for use, damage or loss. **Reason** required.
  - **Correct count**: enter what you counted; the system records the difference. **Reason** required.
  - **Transfer**: move stock from one store to another (choose **To store**).
- **Edit** an item to change prices, add sizes, etc. A size/colour that already has history is kept (made inactive) when removed; one never used is deleted.
- **Make inactive** hides an item from lists and new documents; its history stays. **Delete item** is only possible for an item that never had stock movements.

### 3.2 Stores
- Sidebar → Inventory → **Stores**. Your business starts with a default store.
- **Add store** (name, code, address). **Edit store** to rename or make inactive. **Make default** chooses where new stock goes by default.
- The default store cannot be made inactive or deleted. A store with history cannot be deleted (make it inactive).
- Your package may limit how many stores you can have.

### 3.3 Stock history
- Sidebar → Inventory → **Stock history**: every stock change, newest first: date, item, store, type, quantity (+ in / − out), **Balance** after, reason, reference (order or invoice number), and who did it.
- Filters: store, type, dates. Export PDF/Excel. Nothing here can be edited or deleted: mistakes are fixed with a new correction.
- Types you will see: Opening stock, Received, Issued, Correction, Transfer out / Transfer in, Sale, Sale return, Production in, Used in production, Purchase.

### 3.4 Inventory settings
- Sidebar → Inventory → **Inventory settings**: the list of **Units** (one per line) offered when adding items.

### Good to know (Inventory)
- Stock can never go below zero. If you try, the message says how many are available and how many were needed, and nothing is saved.
- Stock on hand is always the total of the stock history.

---

## 4. Sales

### 4.1 Quotations and samples
- Sidebar → Sales → **Quotations**. Buttons: **+ New quotation** and **+ New sample**.
- New quotation: **Customer**, **Date**, **Valid until**, **Follow up on**, **Store**, **Discount**, product lines, **Notes**. Tick **This is a sample request** for a sample.
- Product lines (same everywhere: quotes, orders, invoices, purchases):
  - **Add line**, choose the **Product**, **Description** (optional), **Qty**, **Unit price** (filled from the product's price; can be changed). The total updates as you type.
  - **Add by size × colour grid**: choose a product with sizes/colours, type quantities in the grid, **Add these lines**.
- Quotation page:
  - **Mark as**: Open, Accepted, Rejected. Badges show **Expired** (past "Valid until") and **Follow up due**.
  - **Deposits**: **+ Add deposit** (amount, date, method, reference). Several deposits can be added; a wrong one is **Reverse**d with a reason. Deposits are applied automatically to the invoice of the order later.
  - **Convert to order**: creates a pending sales order with the same customer, products, descriptions, prices and discount. The quotation becomes "Converted to order".
- Samples: status **Requested** → **Sent to customer** (the sample pieces leave stock) → **Approved** or **Rejected** → **Returned to stock** (the pieces come back). An approved sample always shows "Approved".
- List filters: status, kind (quotes / samples), **Follow-up due**.

### 4.2 Sales orders
- Sidebar → Sales → **Sales orders** → **+ New order** (or from a customer's page, or by converting a quotation).
- Fields: **Customer** (link "+ Add a customer" if new), **Order date**, **Due date**, **Deliver from** (store), **Customer reference** (their PO number), **Discount**, products, **Notes**.
- Statuses and actions (order page, right side):
  - **Pending**: everything can be edited. **Confirm order** locks the products and reserves the stock.
  - **Confirmed** / **In production** / **Ready**: move between them with **Move to**. The date and notes can still change; products cannot.
  - **Partly delivered**: part has been delivered (see Deliveries).
  - **Mark delivered**: takes the products out of the order's store (everything still to deliver).
  - **Delivered**: finished.
  - **Cancel order** (with a reason): only before anything was delivered. Reserved stock becomes free again.
- The order page shows how much of each product is in the store, panels for **Work orders**, **Deliveries** and **Invoices**, and buttons **Make in production**, **Deliver**, **Invoice this order**.
- List: filters by status, dates, **Overdue only** (due date passed and not delivered). Export PDF/Excel.

### 4.3 Deliveries (delivery notes)
- From an order page: **Deliveries** panel → **Deliver**. Or Sidebar → Sales → **Deliveries** to see all notes.
- Enter **Delivering now** per product (**Fill in everything left** helps), **Date**, **Received by**, **Receiver phone**, **Vehicle**, and the **Signature**: the receiver signs in the box with a finger or mouse (**Clear** to redo).
- Saving takes those quantities out of stock. The rest stays reserved for a later delivery. You cannot deliver more than is left.
- Delivery note page: **PDF** to print with the signature.

### 4.4 Invoices and customer payments
- Sidebar → Sales → **Invoices** → **+ New invoice** (or **Invoice this order** on an order, or **+ New invoice** on a customer).
- Fields: **Customer**, **Invoice date**, **Due date**, **Goods from** (store), **Discount**, products, **Notes**. Tax is added at your business rate.
- Direct invoice (not from an order): saving takes the products out of the store. For an order, the goods leave with the order (delivering it if needed), never twice. An order can be invoiced only once.
- Invoice page:
  - **PDF**: the invoice with your details, TIN and payment instructions.
  - **Record payment**: amount, date, method (Cash, Mobile money, Bank transfer, Cheque, Card, Other), reference. Several payments are allowed. A payment above the balance is refused.
  - Statuses: **Unpaid**, **Part paid**, **Paid**, **Cancelled**. Badge **Overdue** when the due date passed with a balance.
  - **Reverse** a payment entered by mistake (with a reason); it stays in the list marked "Reversed".
  - **Cancel invoice** (with a reason): only after reversing any payments. It creates a **credit note** (number "CN-…"). Tick **Put the goods back in stock** if they came back.
- The list header shows how much is still owed on open invoices. Filters: status, dates, **Overdue only**. Export PDF/Excel.

### 4.5 Invoice settings (owner/accountant)
- Sidebar → Sales → **Invoice settings**: **Tax rate (%)** (e.g. 18), **Your TIN**, **Phone on invoices**, **Address on invoices**, **Payment instructions / footer** (bank or mobile money details). Printed on every PDF invoice.

---

## 5. Production

### 5.1 Work orders
- Sidebar → Production → **Work orders** → **+ New work order** (or **Make in production** on a sales order).
- Fields: **Product** (size/colour), **Quantity to make**, **Finished goods go to** (store), **Sales order** (optional link), **Production line** (e.g. "Line A"), **Daily target**, **Start date**, **Due date**, **Notes**.
- The list shows progress bars and **Finished today** per line against the daily target. Filters: status (Open by default), line, search.

### 5.2 Recording progress
- Work order page → **Record count**: choose the **Stage**, the **Quantity** that passed it, the **Date**, a **Note**. Default stages: Cutting → Sewing → Finishing → Packing (your business can change them).
- The last stage puts the finished pieces into stock (only the new ones each time).
- To fix a mistake, enter a minus number with a note.
- When all pieces are finished (or finished + rejected), the work order becomes **Completed**. A linked sales order moves to **In production** when work starts and **Ready** when its work orders are done.
- **Count history** on the page lists every entry, who entered it and when.

### 5.3 Quality control (rejects)
- Work order page → **Quality control** → **Rejected pieces** and **Reason** → **Record rejects**. Rejected pieces never go into stock.

### 5.4 Materials
- Work order page → **Materials used** → **Issue materials**: store, materials and quantities. They leave stock for this work order.
- For products with a style (see Garments), the panel **Materials for style …** shows what is **Needed**, **Issued** and **Still to issue**; **Issue what is still needed** issues exactly the remainder.

### 5.5 Closing or cancelling
- **Close work order**: when fewer pieces will be made. Finished pieces stay in stock.
- **Cancel work order** (with a reason).

### 5.6 Production stages
- Sidebar → Production → **Production stages**: one stage per line, in order. The last stage puts pieces into stock. Existing work orders keep their stages.

### Good to know (Production)
- A stage can never have more pieces than the stage before it (the first stage: never more than the quantity to make).
- A stage can never go below the next stage.
- Finished + rejected can never be more than came out of the stage before.
- Lowering the finished count takes those pieces back out of stock; if they were already sold, it is refused.

---

## 6. Garments

### 6.1 Styles (bill of materials and costing)
- Sidebar → Garments → **Styles** → **Add style**: **Style code**, **Name**, **Product made** (the finished item with its sizes), **Labour per piece**, **Overhead per piece**, **Description**.
- **Pay per piece**: what a worker earns per piece at each stage (e.g. Sewing 450).
- **Bill of materials**: per piece, each material, quantity, **Waste %** (added on top), note. **Add material** for more rows.
- Style page: **Cost per piece** (materials + labour + overhead) and **Margin by size** against each size's sale price. A warning appears if a material has no cost price.
- One style per product.

### 6.2 Piece work, subcontracting and bundles (on the work order)
These panels appear on the work order page of a product that has a style.
- **Piece work**: choose the **Worker**, **Stage**, **Quantity**, **Date** → **Record piece work**. The pieces are counted on the work order (same stage rules) and the worker earns the style's rate.
- **Subcontracting**: **Send to subcontractor**: name, phone, **Stage** done outside, **Quantity** (only pieces waiting for that stage), **Rate per piece**, **Sent on**, **Due back**. When pieces come back, enter the quantity and **Receive back**: they are counted at that stage. Sidebar → Garments → **Subcontracting** lists all jobs with what is **Owed**.
- **Bundles**: **Pieces per bundle** → **Make bundles** splits the cut pieces into numbered bundles. **Print tickets** opens printable bundle tickets.

### 6.3 Workers & pay
- Sidebar → Garments → **Workers & pay**: **Add worker** (name, phone, line); open a worker to edit or make inactive.
- **Pay for the period**: choose **From** and **To** → **Show**: pieces, **Earned**, **Unpaid** per worker. **Mark paid** marks the unpaid amount of that period as paid (asks for your password).

### 6.4 Measurements (tailoring)
- On a customer's page → **Measurements** → **+ Add measurements**: name (e.g. "Wedding dress"), date, values in cm (Chest, Waist, Hips, Shoulder, Sleeve, Length, Neck, Inseam), notes.

---

## 7. Purchasing

### 7.1 Suppliers
- Sidebar → Purchasing → **Suppliers** → **Add supplier**: name, contact person, phone, email, TIN, payment terms, address, notes.
- Supplier page: their purchase orders, **You owe** (goods received minus payments), **Record payment** (cannot be more than you owe), and **Reverse** for a wrong payment.

### 7.2 Purchase orders and goods received
- Sidebar → Purchasing → **Purchase orders** → **New purchase order**: **Supplier**, **Deliver to** (store), **Order date**, **Expected**, products with the **unit cost**, notes.
- **Draft** can be edited. **Mark as ordered** locks it.
- When goods arrive: on the order page, enter **Arrived now** per product, **Received on**, **Supplier delivery note**, then **Receive goods**. Stock goes in at the order cost. Goods can arrive in several parts (**Partly received**), never more than ordered (**Received** when complete).
- **Cancel purchase order**: only before goods arrived.

### 7.3 Reorder list
- Sidebar → Purchasing → **Reorder list**: items at or below their reorder level, counting what is already on order, with a **Suggested** quantity. Adjust quantities and **Order selected** to start a purchase order.

---

## 8. Reports
- Sidebar → Reports → **Reports**. Choose a tab, set **From** and **To**, **Show**. Export with **PDF** or **Excel**.
- **Sales by product**: quantity, revenue, cost, margin; totals for invoices, sales, tax, paid, outstanding (cancelled invoices are ignored).
- **Sales by customer**: invoices, sales, paid, outstanding per customer.
- **Production output**: finished and rejected pieces per day and line, reject rate.
- **Stock value**: current stock at cost per item and store; totals for finished goods and materials.
- **Stock movements**: in and out by type for the period.
- **Purchases**: value of goods received per supplier.
- Reports are built from history, so a past period always gives the same result. You only see the reports your role allows.

---

## 9. Billing (your subscription)
- Sidebar → Billing → **Subscription**: your package, what it includes, its limits (users, stores) and usage, your invoices and payments.
- To pay an invoice: open it, pay by bank or mobile money using the instructions shown, then fill the payment form (amount, date, reference) and attach the **Proof of payment** (photo or PDF) → **Send for approval**. You are notified when it is approved.
- **Choose a plan** to change package. If a payment is late, there is a grace period; after it, the system is locked until payment (your data is kept).

---

## 10. Rules the system enforces (summary for the manual)
- Stock never goes below zero; nothing is saved when stock is short.
- Two lines of the same product on one document cannot together take more than is in stock.
- Products on an order can only be changed while it is **Pending**.
- An order can be invoiced once; goods leave stock once.
- A payment can never be more than what is owed (customers and suppliers).
- An invoice is never deleted: it is cancelled with a credit note, after reversing its payments.
- Production stages only move forward; a later stage never exceeds an earlier one; rejects never enter stock.
- Customers, items, stores and suppliers with history are made inactive, never deleted.
- Every action is recorded in the Audit Logs.
- Document numbers are automatic per year and never reused: Quotation QT-, Sales order SO-, Work order WO-, Delivery note DN-, Invoice INV-, Receipt RC-, Credit note CN-, Purchase order PO-, Goods received GRN-, Supplier payment SP-, Subcontracting CMT-.

## 11. Glossary
- **Variant**: one size/colour of an item. **Reserved**: promised on confirmed orders, not yet delivered. **Available**: on hand minus reserved. **Reorder level**: quantity at which an item counts as low stock. **Bill of materials (BOM)**: materials one piece needs. **Waste %**: extra material for cutting losses. **Piece rate**: pay per piece per stage. **CMT**: work sent to an outside tailor (cut, make, trim). **Bundle**: a group of cut pieces moving together. **Credit note**: document cancelling an invoice. **Deposit**: advance payment on a quotation.

## 12. FAQ (ideas for the writer to expand)
- I cannot see a menu: your role does not allow it, or your package does not include it. Ask your administrator.
- "Not enough … in …": the store does not have that quantity; receive stock, transfer it, or deliver less.
- I entered the wrong production count: enter a minus number with a note.
- I recorded a payment on the wrong invoice: **Reverse** it (with a reason) and record it on the right one.
- A customer returned goods from a cancelled sale: cancel the invoice and tick **Put the goods back in stock**.
- How do I know what happened to an order? Check its page and Sidebar → System → **Audit Logs**.
