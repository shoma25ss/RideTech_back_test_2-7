# 2-7-1 注文登録ミニアプリ（骨組み版）

このスケルトンから **CSRF 検証 / サーバ側バリデーション / 取引登録（orders + order_items） / PRG / 結果表示** を実装します。  
**Bootstrap は使わず、自作 CSS（`assets/styles.css`）を利用**します。

## 目標（MUST）

- `orders/new.php` のフォームから**注文を作成**できること
- `orders/create.php`（実装課題）
  - [ ] CSRF トークンを `hash_equals` で検証（`verify_csrf()` 利用）
  - [ ] `user_id` の実在チェック（`users` に存在）
  - [ ] 数量は **整数・0〜99**、**少なくとも 1 つが 1 以上**
  - [ ] 単価は **DB の `products.price` を使用**（フォーム値は信用しない）
  - [ ] **トランザクション**で `orders` → `order_items[*]` を登録し **commit / rollback**
  - [ ] 成功時は **PRG (303 See Other)** で `show.php?id=...` へ
  - [ ] 失敗時は **エラー表示＋入力保持（sticky）**
- 一覧・詳細表示の安全性
  - [ ] `orders/index.php` で **ページング（20 件/頁・降順）**
  - [ ] `orders/show.php` で **ヘッダ＋明細**を表示、**404/500 を適切に返す**
  - [ ] 画面の **出力はすべてエスケープ**（`e()` = `htmlspecialchars(..., ENT_QUOTES)`）

## 構成

```
.
├─ README.md
├─ .env.example            # DB接続サンプル（.env にコピーして編集）
├─ assets/
│  └─ styles.css           # 自作CSS（編集可）
├─ common/
│  ├─ db.php               # PDO接続（そのまま使用）
│  └─ security.php         # CSRF/エスケープ/フラッシュ等のヘルパ（使用推奨）
└─ orders/
├─ index.php            # 一覧（★骨組み＋TODO）
├─ new.php              # フォーム（★骨組み＋TODO）
├─ create.php           # 作成（★骨組み＋TODO：本課題の中心）
└─ show.php             # 詳細（★骨組み＋TODO）

```

## 実装範囲（ファイル別 TODO）

### 1) `orders/index.php`（一覧）

- GET `?page=N` を整数化し、**1 未満は 1** に丸める
- 1 ページ **20 件**、**`order_date DESC, id DESC`** の順
- `orders o` と `users u` を **JOIN** して  
  `o.id, u.name AS user_name, o.order_date, o.total_amount` を取得
- **次ページ判定**のため \*\*`perPage+1` 件取得 → `array_pop()` で余剰 1 件を捨てる
- 出力は `e()` でエスケープ

> ⚠️ MySQL の実プリペアドは `LIMIT / OFFSET` のプレースホルダ不可（`PDO::ATTR_EMULATE_PREPARES=false` のため）。  
> **方法 A（推奨）**：`$perPage` と `$offset` を **こちらで整数計算**し、**整数のみ**を SQL に埋め込む。  
> **方法 B**：このクエリに限り一時的に `ATTR_EMULATE_PREPARES=true` にして `:limit/:offset` をバインド。

### 2) `orders/new.php`（新規フォーム）

- `users(id, name)` と `products(id, name, price)` を **DB から取得**して表示
- 数量入力は **0〜99**（0 は未選択）
- **CSRF hidden** を埋め込む（雛形済み）
- **エラー時**は `flash_get('errors')` と `flash_get('old')` で **メッセージと入力値を保持（sticky）**

### 3) `orders/create.php`（作成 POST）

- **CSRF 検証** → 失敗時は 400 相当で拒否
- **入力検証**
  - `user_id` が `users` に存在
  - 数量は整数・0〜99、**1 つ以上が 1 以上**
- **単価は DB から取得**（`IN (...)` で対象 `product_id` 一括取得）
- **サーバ側で合計計算**（`qty * unit_price` の総和）
- **トランザクション**
  1. `orders(user_id, order_date=CURRENT_DATE, total_amount)` を `INSERT`
  2. `order_items(order_id, product_id, qty, unit_price)` を **明細分ループで INSERT**
- 例外時は **rollback** ＋ `error_log(...)`、`flash_set(errors, old)` して `new.php` へ
- 成功時は **303 PRG** で `show.php?id=...` にリダイレクト

### 4) `orders/show.php`（詳細）

- GET `id` が **正の整数**か検証。1 未満は **400**
- `orders o` と `users u` を **JOIN** して **ヘッダ**取得（見つからなければ **404**）
- `order_items oi` と `products p` を **JOIN** して **明細**取得  
  表示：商品名 / 数量 / 単価 / 小計（`qty * unit_price`）
- 出力は `e()`、金額は `number_format((float)$value)`

## 推奨の実装順

1. **`index.php`**：ページング & JOIN（画面が動くと開発しやすい）
2. **`new.php`**：ユーザー/商品一覧の取得 & CSRF hidden
3. **`create.php`**：CSRF → 検証 → 単価取得 → 合計計算 → 取引登録 → PRG
4. **`show.php`**：ヘッダ/明細の取得、404/500 ハンドリング

## 受け入れチェック（最低限）

- [ ] 正常系：2 商品（例：2,1）で作成 → `show.php` に**正しい小計と合計**が表示
- [ ] CSRF 改ざん → **拒否**（400 相当 or エラーメッセージ）
- [ ] 数量不正（負数 / 100 以上 / 全て 0）→ エラー表示 **＋ sticky**
- [ ] 一覧：`index.php` に最新の注文が表示（**降順**・**20 件/頁**・詳細リンク動作）
- [ ] XSS：`users.name` に `<script>` を埋めても**実行されない**（エスケープ済み）

## 注意・ヒント

- **SQL の文字列連結は禁止**。ただし **方法 A** で **自前計算した整数の `LIMIT/OFFSET` を直接埋め込む**のは OK
- `security.php` のユーティリティを活用
  - `csrf_field()` / `verify_csrf()` / `e()` / `flash_get()` / `flash_set()` / `redirect_see_other()`
- `PDO` は `ERRMODE_EXCEPTION`、`FETCH_ASSOC`、`EMULATE_PREPARES=false`（`common/db.php` 参照）
- `IN (...)` のプレースホルダは `array_fill` で作成 → `execute(array_values(...))`
- 例外時は `error_log('[tag] '.$e->getMessage())` で原因追跡

## 動かし方（最短）

1. `.env` を作成

   ```bash
   cp .env.example .env
   # DB_HOST=db / DB_PORT=3306 / DB_NAME=appdb / DB_USER=app / DB_PASS=app
   ```

2. Docker 起動（用意した compose を利用）

   ```bash
   docker compose up -d --build
   ```

3. アプリにアクセス
   • http://localhost:8080/orders/new.php
   • （任意）http://localhost:8081 で Adminer
