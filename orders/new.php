<?php
declare(strict_types=1);
require __DIR__.'/../common/security.php';
require __DIR__.'/../common/db.php';
ensure_session();

/**
 * ここでは学習用に「フォーム（CSRF）＋ 初期表示データ取得（users/products）＋ sticky」を
 * 自分で実装してもらう骨組みにしています。
 *
 * 【実装要件（MUST）】
 * - フォームに CSRF トークン（hidden）を埋め込む（※このファイルに雛形あり）
 * - users テーブルから id, name を取得し、<select> に一覧表示
 * - products テーブルから id, name, price を取得し、数量(0〜99)入力欄を並べる
 * - エラー時は flash('errors','old') を使ってメッセージと入力値を保持（sticky）
 * - 出力はすべて htmlspecialchars（= e()）
 */

// ---------- STEP 0: フラッシュ取得（完成済み／編集可） ----------
$errors = flash_get('errors', []);
$old    = flash_get('old', ['user_id'=>'', 'qty'=>[]]);

// ---------- STEP 1: users を DB から取得（TODO） ----------
// 要件：users テーブルから id, name を取得し、id の昇順で並べる
$users = [];
try {
  $pdo = pdo();

  $sql = "
  SELECT
  id,
  name
  FROM
  users
  ORDER BY
  id ASC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute();
    // ここでusersを取得してください
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[orders/new users] '.$e->getMessage());
}

// ---------- STEP 2: products を DB から取得（TODO） ----------
// 要件：products テーブルから id, name, price を取得し、id の昇順で並べる
$products = [];
try {
  $pdo = pdo();

  $sql = "
  SELECT
  id,
  name,
  price
  FROM
  products
  ORDER BY
  id ASC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute();
    // ここでproductsを取得してください
  $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[orders/new products] '.$e->getMessage());
}
?>
<!doctype html>
<html lang="ja">
<meta charset="utf-8">
<?= csp_meta() ?>
<link rel="stylesheet" href="../assets/styles.css">
<title>新規注文</title>
<body>
  <div class="container">
    <div class="header">
      <div class="title">
        <h1>新規注文</h1>
        <span class="badge">CSRF+検証+取引</span>
      </div>
      <div class="button-row">
        <a href="index.php"><button class="secondary">一覧へ</button></a>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="card error">
        <strong>入力エラーがあります。</strong>
        <ul><?php foreach ($errors as $m): ?><li><?= e((string)$m) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <div class="card">
      <!-- TODO: action の先は create.php（MUST） -->
      <form action="create.php" method="post" class="form-grid" novalidate>
        <!-- STEP 3: CSRF トークン（MUST／この hidden は消さない） -->
        <?= csrf_field() ?>

        <label>ユーザー
          <select name="user_id" required>
            <option value="">-- 選択 --</option>
            <?php if ($users): ?>
              <?php foreach ($users as $u): ?>
                <option value="<?= e((string)($u['id'] ?? '')) ?>"
                  <?= (string)$old['user_id'] === (string)($u['id'] ?? '') ? 'selected' : '' ?>>
                  <?= e(($u['id'] ?? '').': '.($u['name'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            <?php else: ?>
              <!-- TODO: users 取得を実装するとこの案内は消えます -->
              <option value="" disabled>（ユーザー一覧が未取得です：DB 取得を実装してください）</option>
            <?php endif; ?>
          </select>
        </label>

        <div>
          <label>商品（数量を入力：0は未選択）</label>
          <table>
            <thead><tr><th>ID</th><th>商品名</th><th style="text-align:right;">単価</th><th>数量</th></tr></thead>
            <tbody>
              <?php if ($products): ?>
                <?php foreach ($products as $p):
                  $pid = (string)($p['id'] ?? '');
                  $qty = (int)($old['qty'][$pid] ?? 0);
                ?>
                  <tr>
                    <td><?= e($pid) ?></td>
                    <td><?= e((string)($p['name'] ?? '')) ?></td>
                    <td style="text-align:right;">¥<?= number_format((float)($p['price'] ?? 0)) ?></td>
                    <!-- STEP 4: 数量は 0〜99（MUST） -->
                    <td><input type="number" name="qty[<?= e($pid) ?>]" value="<?= $qty ?>"></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <!-- TODO: products 取得を実装するとこの案内は消えます -->
                <tr><td colspan="4" class="small">商品一覧が表示されていません。DB からの取得を実装してください。</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="button-row">
          <button type="submit">注文を作成</button>
          <a href="index.php"><button class="secondary" type="button">キャンセル</button></a>
        </div>
        <p class="small">
          単価はサーバ側で参照します。フォーム値が改ざんされても使用しません（MUST）。
        </p>
      </form>
    </div>
  </div>
</body>
</html>