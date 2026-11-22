<?php
function getDbConnection()
{
	// config.phpへのパスを絶対パスで指定
	$configPath = '/home/naojima/www/lib/config.php';

	// ファイルが存在するかチェック
	if (!file_exists($configPath)) {
		// エラーログなどに出力し、処理を終了
		error_log('Configuration file not found.');
		return null;
	}
	$config = require $configPath;

	// DSN (Data Source Name) を作成
	$dsn = "{$config['db_type']}:host={$config['db_host']};dbname={$config['db_name']}";

	// SSL設定をDSNに追加
	// 各キーに 'ssl' をプレフィックスとして追加
	if (!empty($config['db_ssl'])) {
		foreach ($config['db_ssl'] as $key => $value) {
			$dsn .= ";ssl{$key}={$value}";
		}
	}

	try {
		$pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // デフォルトで連想配列で取得
		]);
		return $pdo;
	} catch (PDOException $e) {
		// エラーログなどに出力し、nullを返す
		error_log("Database connection error: " . $e->getMessage());
		echo "データベース接続に失敗しました。";
		return null;
	}
}

