<?php
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$email='admin@ibadeg.local';
if(App\Models\User::where('email',$email)->exists()){echo "Administrador já existente; credencial preservada.\n";exit;}
$password='Ibadeg!'.bin2hex(random_bytes(8));
$u=new App\Models\User;$u->forceFill(['name'=>'Administrador IBADEG','email'=>$email,'password'=>$password,'role'=>'admin','status'=>'approved'])->save();
file_put_contents(dirname(__DIR__).'/ACESSO-LOCAL.txt',"IBADEG — acesso local\r\nURL: http://127.0.0.1:8013\r\nE-mail: $email\r\nSenha: $password\r\n\r\nAltere a senha em Minha conta após entrar.\r\n");
echo "Administrador criado. Credencial salva em ACESSO-LOCAL.txt.\n";
