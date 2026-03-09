<?php
declare(strict_types=1);
require __DIR__.'/../common/security.php';
require __DIR__.'/../common/db.php';
ensure_session();

/**
 * 下の TODO を埋めて、正常動作させてください。
 */

// ---------- STEP 0: メソッド制御 ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

// ---------- STEP 1: CSRF チェック（TODO） ----------
// ヒント: security.php の verify_csrf() を使う
// if ( ... ) { http_response_code(400); exit('Bad Request (CSRF)'); }
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
  http_response_code(400);
  exit('Bad Request (CSRF)');
}

// ---------- STEP 2: 入力取得とバリデーション（TODO） ----------
$errors = [];
$user_id = (string)($_POST['user_id'] ?? '');
$qtyPost = $_POST['qty'] ?? [];               // qty は ["product_id" => "数量"] の配列で飛んでくる
$qtys    = is_array($qtyPost) ? $qtyPost : []; // 異常系に備え防御的に

// 2-1) users テーブルに user_id が存在するか確認（PDOでSELECT）
if ($user_id === '' || !ctype_digit($user_id)) {
  $errors[] = 'ユーザーを選択してください';
}

// 2-2) 数量の検証：整数・0〜99のみ許可、1つ以上が1以上であること
$selected = []; // [ product_id(int) => qty(int) ]
foreach ($qtys as $product_id => $qty) {
  if (!ctype_digit((string)$product_id)) {
  continue;
 }

if (!ctype_digit((string)$qty)) {
  $errors[] = '数量は整数で入力してください';
  continue;
 }

$q = (int)$qty;
// 上記チェックで NG の場合は $errors[] に日本語メッセージを積む
if ($q < 0 || $q > 99) {
  $errors[] = '数量は0~99で入力してください';
  continue;
 }

if ($q > 0) {
  $selected[(int)$product_id] = $q;
 }
}

if (count($selected) === 0) {
  $errors[] = '商品を1つ以上選択してください';
}
// ---------- バリデーションNG時の処理（完成済み） ----------
if ($errors) {
  flash_set('errors', $errors);
  flash_set('old', ['user_id' => $user_id, 'qty' => $qtys]);
  redirect_see_other('new.php');
}

// ---------- STEP 3: 単価を DB から取得（TODO） ----------
// ヒント：IN 句で該当 product_id 一覧を取得 → id, name, price を配列で受ける
// $products = [...];
// 取得件数が $selected 件未満なら、存在しない商品IDが混じっている → エラーにして new.php へ
$ids = array_keys($selected);

$placeholders = implode(',', array_fill(0, count($ids), '?'));

$sql = "
SELECT
id,
name,
price
FROM
products
WHERE
id IN ($placeholders)
";

$pdo = pdo();
$stmt = $pdo->prepare($sql);
$stmt->execute($ids);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($products) !== count($selected)) {
  $errors[] = '存在しない商品が含まれています';

  flash_set('errors', $errors);
  flash_set('old',[
    'user_id' => $user_id,
    'qty' => $qtys
  ]);
  header('Location: /orders/new.php');
  exit;
}

// ---------- STEP 4: 合計金額のサーバ計算（TODO） ----------
$total = 0.0;
$map   = []; // [ product_id => ['qty'=>..., 'unit_price'=>...] ]
// foreach ($products as $p) { ... } で $total と $map を作る
foreach ($products as $p) {
  $product_id =(int)$p['id'];
  $price = (float)$p['price'];

  $qty = $selected[$product_id];

  $total += $price * $qty;

  $map[$product_id] = [
    'qty' => $qty,
    'unit_price' => $price
  ];
}
// ---------- STEP 5: 取引（トランザクション）で登録（TODO） ----------
// 5-1) beginTransaction()
$pdo = pdo();
try {
  $pdo->beginTransaction();
// 5-2) orders に (user_id, order_date=今日, total_amount) を INSERT → lastInsertId() を取得
  $sql = "
  INSERT INTO orders
  (user_id, order_date, total_amount, created_at)
  VALUES
  (?, CURDATE(), ?,NOW())
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    $user_id,
    $total
  ]);

  $order_id = (int)$pdo->lastInsertId();
// 5-3) order_items に (order_id, product_id, qty, unit_price) を ループで INSERT
$sql = "
INSERT INTO order_items
(order_id, product_id, qty, unit_price)
VALUES
(?, ?, ?, ?)
";

$stmt = $pdo->prepare($sql);

foreach ($map as $product_id => $row) {

$stmt->execute([
  $order_id,
  $product_id,
  $row['qty'],
  $row['unit_price']
]);
}
// 5-4) commit()
$pdo->commit();
// 失敗時は catch(Throwable) で rollback() → ログ出力 → フラッシュして new.php に戻る
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log('[orders/store] '.$e->getMessage());

  flash_set('errors', ['注文の保存に失敗しました']);
  flash_set('old', [
    'user_id' => $user_id,
    'qty' => $qtys
  ]);
  header('Location: /orders/new.php');
  exit;
}
// ---------- STEP 6: PRG（完成済み） ----------
// redirect_see_other('show.php?id=' . $order_id);
redirect_see_other('show.php?id=' . $order_id);
// ここまで実装できたら、/orders/show.php?id=... で結果が見えるはずです。
