# システム構成・仕様書

## プロジェクト概要

<!-- プロジェクト名と概要を記入 -->
- **プロジェクト名**: ImageMagick MCPサーバー
- **説明**: ImageMagick を操作する HTTP ベースの MCP サーバー
- **バージョン**: 0.1.0
- **最終更新**: 2026-05-04

---

## システムアーキテクチャ

### システム構成図

```
<!-- システムの全体構成を記入 -->
```

### コンポーネント一覧

| コンポーネント | 役割 | 技術スタック |
|---|---|---|
| クライアント (MCP Host) | 画像変換リクエストを送信 | MCP対応クライアント |
| Nginx | HTTP受信、リバースプロキシ | Nginx |
| PHP-FPM | PHPアプリ実行環境 | PHP-FPM 8.4 |
| フロントコントローラ | リクエストの入口 | PHP, opt/app/public/index.php |
| Action層 | ユースケース実行、ImageMagick呼び出し制御 | PHP, opt/app/action |
| Responder層 (MCP) | MCPレスポンス生成 | PHP, opt/app/responder/mcp.php |
| Responder層 (Browser) | ブラウザ向けレスポンス生成 | PHP, opt/app/responder/browser.php |
| ImageMagick実行基盤 | 画像変換・加工処理を実行 | ImageMagick CLI |
| 設定管理 | DIやアプリ設定の提供 | PHP, opt/app/config/config.php, opt/app/config/services.php |
| プロセス管理 | PHP-FPM/Nginxの監視・起動 | Supervisor, etc/supervisord.conf |

---

## ディレクトリ構造

```
<!-- プロジェクトのディレクトリ構造を記入 -->
root/
├── docker-compose.yml                                   # コンテナ構成定義
├── Dockerfile                                           # アプリケーション環境
├── etc/                                                 # 設定ファイル
│   ├── composer.json                                    # PHP依存パッケージ定義
│   ├── composer.lock                                    # PHP依存パッケージロック
│   ├── nginx.conf                                       # Webサーバー設定
│   ├── php-fpm.conf                                     # PHP-FPM設定
│   └── supervisord.conf                                 # プロセス管理設定
└── opt/
    └── app/                                           # アプリケーション本体
        ├── action/                                    # アクション処理層
        │   └── imagick/                               # Imagick関連Actions
        │       ├── transforms.php                     # 描画命令登録Action
        │       └── preview.php                        # 画像プレビュー生成Action
        ├── config/                                    # 設定ファイル関連
        │   ├── config.php                             # 設定ファイル
        │   └── services.php                           # ライブラリなどの定義
        ├── responder/                                 # レスポンス処理層
        │   ├── browser.php                            # ブラウザアクセス時のレスポンス処理
        │   └── mcp.php                                # MCPアクセス時のレスポンス処理
        └── public/                                    # 公開ディレクトリ
            └── index.php                              # フロントコントローラ
```

---

## 技術スタック

### バックエンド

| 技術    | バージョン | 用途        |
|:--------|:-----------|-------------|
| PHP     | 8.4.20-r1  | メイン言語  |
| Nginx   | 1.28.3-r0  | Webサーバー |
| PHP-FPM | 8.4.20-r1  | PHPワーカー |

### ツール・ライブラリ

| 名前       | バージョン | 説明                  |
|:-----------|:-----------|-----------------------|
| Composer   | 2.9.3      | PHP依存パッケージ管理 |
| Supervisor | 4.3.0-r0   | プロセス監視・管理    |

### インフラストラクチャ

| 名前           | 説明               |
|----------------|--------------------|
| Docker         | コンテナ化         |
| Docker Compose | マルチコンテナ管理 |

---

## 機能仕様

### 主要機能

#### 描画命令の登録
- **説明**: クライアントから受け取った描画命令列を Redis に保存し、後続のプレビュー・ダウンロードで参照するキャッシュIDを返却する
- **入力**: 描画命令の配列
```php
$input = [
  ['draw' => ['setStrokeWidth', 1]],
  ['draw' => ['setStrokeColor', '#a4610f']],
  ['draw' => ['arc', [300, 300, 200, 200, 300, 360]]],
];
```
- **出力**: キャッシュID
```php
$output = [
  'id' => 'imagick:draw:8f3b7f...',
];
```
- **処理フロー**: 入力された命令配列を JSON として Redis に保存し、保存先キーをキャッシュIDとして返却する

#### 画像プレビュー生成
- **説明**: Redis に保存された描画命令をもとに Imagick / ImagickDraw を実行し、プレビュー用画像を生成する
- **入力**: キャッシュIDと出力条件
```php
$input = [
  'id' => 'imagick:draw:8f3b7f...',
  'canvas' => [32, 32],
];
```
- **出力**: 画像データ
```php
$output = [
  'mime' => 'image/png',
  'blob' => '...binary data...',
];
```
- **処理フロー**: キャッシュIDをもとに Redis から命令列を取得し、ImagickDraw で描画してプレビュー用 blob を返却する

#### 画像ダウンロード生成
- **説明**: Redis に保存された描画命令をもとに、最終出力用の画像を生成する
- **入力**: キャッシュIDと出力条件
```php
$input = [
  'id' => 'imagick:draw:8f3b7f...',
  'canvas' => [1024, 1024],
  'format' => 'png',
];
```
- **出力**: 画像ファイルデータ
- **処理フロー**: プレビュー生成と同じ命令列を使用し、高解像度または指定フォーマットで画像を生成して返却する

---

## API仕様

### エンドポイント一覧

#### POST /mcp/imagick/transforms
- **説明**: 描画命令列を登録し、キャッシュIDを返却する
- **リクエストボディ**:
  ```json
  {
    "draw": [
      { "method":"setStrokeWidth", [1] },
      { "method":"setStrokeColor", ["#a4610f"] },
      { "method":"arc", [300, 300, 200, 200, 300, 360] }
    ]
  }
  ```
- **レスポンス (200)**:
  ```json
  {
    "id": "imagick:draw:8f3b7f..."
  }
  ```
- **エラーレスポンス**:
  - 400: 不正なリクエスト
  - 500: サーバーエラー

#### POST /mcp/imagick/preview
- **説明**: キャッシュIDをもとにプレビュー画像を生成する
- **リクエストボディ**:
  ```json
  {
    "id": "imagick:draw:8f3b7f...",
    "canvas": [32, 32],
    "format": "png"
  }
  ```
- **レスポンス (200)**:
  ```json
  {
    "mime": "image/png",
    "blob": "...binary data..."
  }
  ```
- **エラーレスポンス**:
  - 400: 不正なリクエスト
  - 500: サーバーエラー

#### POST /mcp/imagick/download
- **説明**: キャッシュIDをもとに最終出力用画像を生成する
- **リクエストボディ**:
  ```json
  {
    "id": "imagick:draw:8f3b7f...",
    "canvas": [1024, 1024],
    "format": "png"
  }
  ```
- **レスポンス (200)**:
  ```json
  {
    "mime": "image/png",
    "blob": "...binary data..."
  }
  ```
- **エラーレスポンス**:
  - 400: 不正なリクエスト
  - 500: サーバーエラー

---

## 処理フロー

### リクエスト処理フロー

```
クライアント
    ↓
Nginx (ルーティング)
    ↓
PHP-FPM (リクエスト処理)
    ↓
Action クラス (ビジネスロジック)
    ↓
Responder クラス (レスポンス生成)
    ↓
クライアント
```

---

## セットアップ・デプロイ

### 前提条件

- Docker
- Docker Compose
- Git

### セットアップ手順

1. リポジトリのクローン
   ```bash
   git clone <repository-url>
   cd <project-directory>
   ```

2. 環境設定ファイルの作成
   ```bash
   cp .env.example .env
   ```

3. コンテナの起動
   ```bash
   docker-compose up -d
   ```

4. 依存パッケージのインストール
   ```bash
   docker-compose exec app composer install
   ```

5. アプリケーション初期化
   ```bash
   docker-compose exec app php bin/setup.php
   ```

### 開発環境での実行

```bash
docker-compose up
```

### 本番環境でのデプロイ

```bash
# ビルド
docker build -t <image-name>:<tag> .

# プッシュ
docker push <image-name>:<tag>

# デプロイ
docker-compose -f docker-compose.prod.yml up -d
```

---

## 使用方法

### 基本的な使用例

```bash
# 例
curl -X POST http://localhost:8000/api/example \
  -H "Content-Type: application/json" \
  -d '{"param": "value"}'
```

---

## トラブルシューティング

### よくある問題

| 問題 | 原因 | 解決方法 |
|---|---|---|
| コンテナが起動しない | ポート競合 | `docker ps` で確認、別ポートを使用 |
| PHPエラー | 依存パッケージ不足 | `composer install` を実行 |
| 権限エラー | ファイルパーミッション | `chmod` でパーミッション変更 |

---

## 開発ガイドライン

### コーディング規約

- PHP: [PSR-12](https://www.php-fig.org/psr/psr-12/)
- インデント: スペース4個

### コミットメッセージ形式

```
<type>: <title>

<body>
```

### ブランチ戦略

- `main`: 本番環境ブランチ
- `develop`: 開発ブランチ
- `feature/*`: 機能開発ブランチ

