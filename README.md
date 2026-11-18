# IBADEG

Portal independente do Instituto Educacional Teológico do Gama. Laravel 13, PHP 8.3+, MySQL 8 e interface Blade responsiva. A identidade usa a logo fornecida, bordô, azul e tons de papel.

## Executar nesta máquina

O banco `ibadeg` e uma conta administrativa local foram configurados. As credenciais ficam em `.env` e `ACESSO-LOCAL.txt`, ambos fora do Git.

```powershell
cd D:\HAVNEXT\ibadeg
php artisan serve --host=127.0.0.1 --port=8013
```

Abra http://127.0.0.1:8013. A matrícula está em `/matricula`, o login em `/entrar` e a administração em `/admin`.

## Instalar em outro ambiente

1. Instale PHP 8.3+ com PDO MySQL, mbstring, XML, cURL, fileinfo, OpenSSL e ZIP, Composer e MySQL 8.
2. Execute `composer install --no-dev --optimize-autoloader`.
3. Copie `.env.example` para `.env`. Configure `APP_URL`, o banco e uma credencial exclusiva com permissão nesse banco. Em produção, use `APP_ENV=production`, `APP_DEBUG=false`, HTTPS e `SESSION_SECURE_COOKIE=true`.
4. Execute `php artisan key:generate` e `php artisan migrate --seed --force`.
5. Crie o administrador: `php artisan ibadeg:admin seu-email@dominio.com --name="Coordenação IBADEG"`. A senha será solicitada no terminal.
6. Configure o servidor para servir apenas a pasta `public`. Permita escrita em `storage` e `bootstrap/cache`.
7. Após configurar, execute `php artisan optimize`.

O curso e seus materiais acompanham o projeto em `resources/course`. A carga inicial cria 21 disciplinas e 761 itens, sem substituir questões existentes ou apagar tentativas. O front-end usa os arquivos prontos de `public/assets`; não exige compilação de Node para funcionar.

## Matrícula e liberação

O formulário reúne os dados pessoais, endereço, filiação, escolaridade, igreja, pastor, função e núcleo, além do e-mail e senha. O cadastro sempre cria um aluno com acesso **pendente**. Ele pode entrar e acompanhar a matrícula, mas apostilas e provas exigem liberação no servidor.

Em **Alunos e matrículas**, abra o aluno e altere o acesso para **Liberado**. A suspensão bloqueia novas provas, envio de provas e acesso aos materiais. O histórico permanece disponível ao titular. O administrador também pode redefinir a senha.

## Tentativas e correção

- Padrão inicial: 3 tentativas por disciplina e nota mínima 7,00. Ambos podem ser alterados em Configurações.
- Prioridade do limite: aluno → disciplina → curso. Zero impede novas tentativas.
- Uma prova iniciada conta como tentativa. Iniciar novamente retoma a mesma prova enquanto não enviada. O rascunho é salvo no servidor durante o preenchimento.
- As verificações de limite e envio usam transações, locks e índices únicos. Enviar novamente a mesma tentativa não cria outra nota.
- Os enunciados C/E aparecem uma vez, com suas alternativas agrupadas. Há suporte a múltipla escolha, associação e respostas abertas.
- Questões e gabaritos são congelados no início de cada tentativa. O servidor calcula a nota; dados enviados pelo navegador não definem pontuações.
- Cada item avaliado vale 1 ponto. Nota = pontos obtidos ÷ itens avaliados × 10, arredondada para duas casas. A afirmação sem lacuna da disciplina 17 não entra no denominador.
- Respostas abertas e gabaritos duvidosos exigem correção administrativa. A pontuação inicial é identificada como provisória, com itens pendentes valendo zero até a correção. Ela não libera certificado.
- Na correção, atribua de 0 a 1 por item e informe o motivo. Cada revisão cria um registro com respostas, pontuações, responsável, data e motivo. Os registros anteriores são imutáveis pela aplicação.
- Quando há erro, **Verificar no livro** apresenta o trecho da apostila e um link para a página. Há 609 trechos associados; itens sem referência localizada não recebem um trecho inventado. A extração de Homilética usa OCR.
- Os gabaritos vêm do material original. Itens identificados como incertos são encaminhados à revisão docente. A coordenação deve conferir esses critérios antes de concluir a nota.

## Histórico e certificado

O histórico mostra todas as tentativas e revisões. A maior **nota final** por disciplina é usada para aprovação. A administração pode exportar todas as revisões em CSV.

O certificado exige aluno liberado, todas as disciplinas aprovadas e a **carga horária oficial** preenchida nas configurações. A carga horária não foi presumida. A emissão congela nome, curso, horas, notas e referências das revisões usadas. Revisões futuras não mudam um documento já emitido.

O histórico acadêmico, o histórico completo da administração e os certificados emitidos podem ser baixados diretamente em PDF. O PDF do certificado inclui o histórico de conclusão com as notas preservadas na emissão. As opções de impressão e exportação CSV também permanecem disponíveis. A página pública `/validar/{uuid}` confirma o registro sem expor CPF, endereço, e-mail ou respostas. O documento completo é restrito ao titular e à administração.

## Testes

```powershell
php artisan test
```

Os testes padrão usam SQLite em memória. A mesma suíte também foi validada com MySQL 8.4 em banco de testes separado. Nunca configure testes para usar o banco de alunos.

Faça backup regular do MySQL, de `.env` e dos materiais. Preserve a chave `APP_KEY`: ela é usada na criptografia das sessões. Não publique arquivos de credenciais ou o diretório `resources/course` pelo servidor web.
