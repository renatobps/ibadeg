<?php
// Configuração local: cria somente o banco IBADEG e uma credencial exclusiva.
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);
if(is_file($root.'/.env')) {fwrite(STDERR,"Já existe .env. Configure o banco manualmente para preservar as credenciais.\n");exit(1);}
$pdo=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$database='ibadeg';
$tables=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='ibadeg'")->fetchColumn();
if($tables>0){fwrite(STDERR,"O banco ibadeg já possui tabelas. Nenhum dado foi alterado.\n");exit(1);}
$username='ibadeg_'.bin2hex(random_bytes(3));$password=bin2hex(random_bytes(24));
$pdo->exec('CREATE DATABASE IF NOT EXISTS ibadeg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec("CREATE USER '$username'@'127.0.0.1' IDENTIFIED BY '$password'");
$pdo->exec("GRANT ALL PRIVILEGES ON ibadeg.* TO '$username'@'127.0.0.1'");
$key='base64:'.base64_encode(random_bytes(32));
$env="APP_NAME=IBADEG\nAPP_ENV=local\nAPP_KEY=$key\nAPP_DEBUG=false\nAPP_URL=http://127.0.0.1:8013\nAPP_LOCALE=pt_BR\nAPP_FALLBACK_LOCALE=pt_BR\nAPP_FAKER_LOCALE=pt_BR\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=$database\nDB_USERNAME=$username\nDB_PASSWORD=$password\nSESSION_DRIVER=database\nSESSION_LIFETIME=120\nSESSION_ENCRYPT=true\nSESSION_COOKIE=ibadeg_session\nCACHE_STORE=database\nQUEUE_CONNECTION=database\nLOG_CHANNEL=stack\nMAIL_MAILER=log\n";
file_put_contents($root.'/.env',$env);
echo "Banco exclusivo ibadeg configurado. Credenciais gravadas somente no .env.\n";
