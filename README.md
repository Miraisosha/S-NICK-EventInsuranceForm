# S-NICK Event Insurance Form

イベント保険の加入対象者へ個別URLを配布し、本人に必要情報を登録してもらうためのWebアプリです。

## 構成

- `frontend`: Vue 3 / Vite / Bootstrap 5
- `api`: CakePHP 5 / PHP 8.5（本番）
- `database`: MySQL 5.7互換スキーマ
- `docker`: ローカル開発環境

## VS Codeでの開始方法

1. このフォルダをVS Codeで開きます。
2. `.env.example` を `.env` にコピーし、開発用パスワードと `SECURITY_SALT` を変更します。
3. Docker Desktopを起動します。
4. VS Codeのターミナルで `docker compose up --build` を実行します。

起動後のURL:

- フロント: http://localhost:5173
- APIヘルスチェック: http://localhost:8080/api/health
- MySQL: `localhost:3308`
- CSV出力管理画面: http://localhost:5173/admin/export
- イベント管理画面: http://localhost:5173/admin/events

開発確認用の個別URL:

```text
http://localhost:5173/register/demo-token-for-development-only
```

## 個別URLの発行

コンテナ起動後、対象者の氏名を指定して実行します。

```powershell
docker compose exec api bin/cake create_invitation "山田 太郎" --days 30
```

コマンドがLINEなどで配布する個別URLを表示します。URLの生トークンはDBに保存せず、SHA-256ハッシュだけを保存します。

## 登録データのCSV出力

`http://localhost:5173/admin/export` を開き、`.env` の `EXPORT_API_KEY` を入力すると、登録完了済みデータのCSVをAES-256暗号化したZIPでダウンロードできます。ZIPの解凍パスワードには `.env` の `EXPORT_ZIP_PASSWORD` が使われます。ZIP内のCSVはUTF-8 BOM付きのため、Excelで日本語を表示できます。

`.env` を作成していない開発環境では、初期キーは `change-this-export-key`、初期ZIPパスワードは `change-this-zip-password` です。本番利用前に、両方をそれぞれ十分長いランダムな値へ必ず変更してください。出力キーはURLに含めず、ブラウザにも保存しません。ZIPと解凍パスワードは別経路で共有してください。

## イベントマスター

`http://localhost:5173/admin/events` を開き、`.env` の `EXPORT_API_KEY` を管理キーとして入力すると、イベント名・開催日・場所を登録できます。登録したイベントは加入者情報入力画面のプルダウンへ表示され、選択すると開催日と場所が表示されます。

## 登録フロー

1. 個別URLの確認
2. 加入者情報の入力と個人情報取扱いへの同意
3. ブラウザ側およびAPI側のバリデーション
4. 入力内容の確認
5. API側で再検証し、登録・同意日時・規約版を保存
6. 登録完了（同じURLからの二重登録は不可）

## 運用開始前に必ず決めること

- 画面上の問い合わせ先（担当部署、電話番号またはメールアドレス）
- 個人情報の保管期間と削除方法
- 実際の保険会社・代理店へ提供する項目と提供方法
- URLの有効期限と、誤送信・紛失時の失効手順
- 本番環境のHTTPS、アクセスログ方針、DB暗号化、バックアップ、管理者認証
- 本番用の秘密情報（`.env`）をリポジトリ外で安全に管理する方法

表示中の「個人情報の取扱いについて」は初期案です。実際の契約・業務フローに合わせ、運用責任者または法務担当者の確認を受けてください。

## GitHub Actionsから本番へデプロイ

Pull Requestではバックエンド、MySQL 5.7 Migration、フロントエンドビルドを検証します。`main` へマージされた場合だけ、同じ検証に成功した後でお名前.com RSへデプロイします。

GitHubリポジトリの `production` Environmentへ次を登録してください。

Secrets:

- `SSH_PRIVATE_KEY`: お名前.comからダウンロードした `snickdeploy.pem` の全文
- `SSH_KNOWN_HOSTS`: SSH復旧後に取得し、フィンガープリントを確認したknown_hostsの1行
- `DATABASE_URL`: `mysql://ユーザー:URLエンコード済みパスワード@DBホスト:3306/DB名?encoding=utf8mb4`
- `SECURITY_SALT`: 十分に長いランダム値
- `EXPORT_API_KEY`: 管理画面で使う十分に長いランダム値
- `EXPORT_ZIP_PASSWORD`: CSV ZIP解凍用の十分に長いランダム値

Variables（未登録時は下記の既定値を使用）:

- `SSH_HOST`: `www58.onamae.ne.jp`
- `SSH_PORT`: `8022`
- `SSH_USER`: `r1216602`
- `APP_ROOT`: `/home/r1216602/apps/snick-insurance`
- `PUBLIC_DIR`: `/home/r1216602/public_html/insurance.s-nick.com`
- `APP_URL`: `https://insurance.s-nick.com`
- `DEPLOY_ENABLED`: 最初は `false`

現在はお名前.com側でRSプランのSSHが停止されているため、`DEPLOY_ENABLED=false` のままにします。SSH復旧後に接続確認とknown_hostsのフィンガープリント確認を行い、国外アクセス制限を確認したうえで `true` に変更します。秘密鍵、DB接続情報、ZIPパスワードはリポジトリへコミットしません。

デプロイ時は公開領域外へリリースを配置し、CakePHP Migrationを実行してから `/api` のシンボリックリンクとフロント資材を切り替えます。

## 初期DBを作り直す場合

初期スキーマはMySQLボリュームが空のときだけ適用されます。開発データを破棄して作り直す場合のみ、内容を確認したうえで次を実行します。

```powershell
docker compose down -v
docker compose up --build
```
