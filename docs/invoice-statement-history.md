# Invoice Statement 歷史查詢

## 本次範圍

只新增歷史查詢與頁面展示。沒有新增 Refund 操作、PDF 匯出、發送、排程或逐筆 Balance；沒有修改付款流程、permission、資料庫結構或資料，也沒有執行 migration、commit、push、部署。

## 實作與資料來源

- `Invoice::visibleTo()` 搬移原 invoice index 的資料範圍規則，供既有列表與 Statement 共用。Controller 原本的頁面 Gate 保留。
- `Invoice::statementPage()` 使用 UNION ALL 合併 invoice、transaction、wallet transaction 事件；各來源一筆事件只出現一次。只以主鍵連接 wallet reference，不 join 一對多的 fee items。
- Invoice 使用 `total_amount`；付款使用 `transactions.amount_applied`，而非可能包含超額的 `amount_paid`；wallet 歷史使用 `wallet_transactions.amount` 的帶正負金額及 `created_at`，完全不以目前 `wallets.balance` 合成歷史。
- `wallet_transactions.reference_id` 同時支援 invoice ID 與 transaction ID。Wallet 持有人必須與該 invoice 的 tenant user 一致。無法確定的雙重 reference 不擅自歸屬。
- 無法連到 invoice 的 wallet 紀錄只在總覽顯示，非 super-admin 限制為 actual/effective user 建立的 tenant；不會依 tenant 相同就塞入某張 lease。已連到無權查看／軟刪除的 invoice 或 transaction，不當作無 reference 紀錄繞過範圍限制。
- 目前本機 wallet_transactions 沒有歷史資料，因此正負額、不同 reference 類型、無 reference 與既有 refund ledger 的驗證使用隔離的 SQLite memory fixtures；沒有補寫本機資料。
- 現有付款流程把外部付款與 wallet 抵扣混合後套用到 invoice，wallet debit 也可能涵蓋罰款，並沒有足夠欄位精確重建每一筆外部入款分攤。因此頁面分列「Invoice / Applied payment / Wallet movement」，不把三欄加總、不推算現金流總額或 Balance。超額轉入 wallet 不是額外收款，wallet 支付也不再加一次到 applied payment。
- Fee Type 與非空 Description 列出相關 invoice items；不把部分付款猜分到各 Fee Type。
- Related parties 分別標示 billed-to tenant／wallet holder、lease owner（沿用既有 Pay To 關聯）與 template invoice issuer，不把經辦人猜成收款人。
- 付款時間以 payment_date 為準（該欄只存日期），另列 recorded_at，避免捏造付款時分。Invoice 狀態明示為目前狀態，並非歷史快照。

## 頁面行為

- 管理端 lease show：固定顯示 URL 所開啟 lease 的事件，不包括同 tenant 的其他 lease／renewal，也不隨原頁前端的歷史卡片切換。表格上方標示範圍。
- 管理端 invoice index：顯示所有授權範圍內的事件，不受既有 invoice 列表當前 10 筆分頁限制。
- 共用 `x-invoice-statement` Blade：日期起訖、事件類型、Invoice No. 查詢，獨立 `statement_page` 分頁，每頁 20 筆；沒有新增修改／付款按鈕。
- 資料庫查詢、joins 與 detail eager-loading 放在 Invoice model；事件顯示整理放 InvoiceService；兩個 Controller 驗證條件並呼叫 Service。

## 本輪修改檔案

1. `app/Models/Invoice.php`
2. `app/Services/InvoiceService.php`
3. `app/Http/Controllers/InvoiceController.php`
4. `app/Http/Controllers/LeaseController.php`
5. `resources/views/components/invoice-statement.blade.php`
6. `resources/views/adminSide/leases/invoices/index.blade.php`
7. `resources/views/adminSide/leases/show.blade.php`
8. `tests/Feature/InvoiceStatementTest.php`
9. 本紀錄。

## 驗證與限制

- Statement 專用測試：7 tests、70 assertions 通過。涵蓋各權限範圍、staff effective user、Property／Unit／Room owner 關聯、不同 lease、soft deletes、wallet holder 不符、invoice／transaction reference、未連結入款、既有 refund ledger 的只讀顯示、多 Fee Type 不放大金額、超額／wallet 抵扣、日期篩選、分頁、HTML escaping 及目前餘額不變。
- 合約與租客 UI 回歸亦通過；前端合約／IC 預覽 5 tests 通過。租金／押金、Unit Number 括號、Service Package 與 IC PDF 實作未更動。
- 本機 MySQL 只讀查詢成功，當時取得 21 筆 Statement 事件。沒有將資料內容輸出到報告或測試 HTML。
- Blade 編譯、PHP 語法檢查、`git diff --check`、`npm run build` 通過。Build 仍有既有 bundle size／Browserslist 資料過期提示，未更新 dependencies。
- 以實際共用 Blade 與隔離 fixture 渲染桌面及 390px 寬內容；篩選欄位正常換行，表格水平捲動。未實測登入後完整頁面互動／實際帳號切換。
- 擴大執行 `LatePenaltyPaymentTest` 有一項既有失敗：測試要求備註包含計費起訖、frequency、charges，但目前付款程式只寫 `Late penalty for invoice INV-TEST`。使用修改前 HEAD 的 Invoice／InvoiceService 重跑同一測試可重現相同失敗，故未修改領導負責的付款流程。
