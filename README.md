# Fitcontrol

Aplicação em PHP para personal trainers e academias de estúdio acompanharem alunos, fichas de treino e mensalidades.

## Executar com Docker

O Docker Compose lê `DB_PASSWORD` do arquivo `.env` e usa a mesma senha na aplicação e no MySQL. Se você ainda não tem um `.env`, copie `.env.example` para `.env` e substitua o valor de exemplo por uma senha forte. Em seguida, suba os serviços:

```bash
docker compose up --build -d
```

Na primeira execução, o MySQL importa o esquema automaticamente. Crie a conta do Personal:

```bash
read -rsp 'Senha do Personal: ' personal_password
printf '\n'
printf '%s\n' "$personal_password" | docker compose exec -T app php bin/create-personal.php 'Nome do Personal' personal@example.com
unset personal_password
```

Abra [Fitcontrol local](http://127.0.0.1:8000). Use `docker compose down` para parar a aplicação. O volume mantém os dados entre execuções. Use a mesma senha do MySQL ao reiniciar com o volume existente.

## Dados de demonstração

Ao importar o esquema em um banco novo, o Fitcontrol cria cinco alunos, cinco exercícios, cinco fichas, cinco itens de treino e cinco mensalidades. A tabela `users` recebe seis contas, uma para cada aluno e outra para o Personal.

- **Personal** usa `personal@fitcontrol.test`.
- **Aluno** pode usar `ana@fitcontrol.test`.
- A senha das contas de demonstração é `FitcontrolDemo2026!`. Use essas contas apenas em ambiente local.

Para importar somente as tabelas em um banco novo, execute o esquema com `@fitcontrol_demo` desativado na mesma sessão:

```bash
{ printf 'SET @fitcontrol_demo = 0;\n'; cat database/schema.sql; } | mysql -u root -p fitcontrol
```

O [relatório simplificado](docs/Relatorio-Simplificado-Fitcontrol.pdf) reúne o diagrama do banco e as capturas do sistema com esses dados.

## Instalação local sem Docker

Requisitos: PHP 8.2 ou superior com `pdo_mysql` habilitado e MySQL 8.0.16 ou superior.

1. No MySQL, crie o banco e uma conta para a aplicação. Substitua `SUA_SENHA_FORTE` por uma senha própria.

   ```sql
   CREATE DATABASE fitcontrol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'fitcontrol'@'127.0.0.1' IDENTIFIED BY 'SUA_SENHA_FORTE';
   GRANT SELECT, INSERT, UPDATE, DELETE ON fitcontrol.* TO 'fitcontrol'@'127.0.0.1';
   ```

2. Importe o esquema usando uma conta com permissão de criação de tabelas e configure a conexão no terminal.

   ```bash
   mysql -u root -p fitcontrol < database/schema.sql
   export DB_DSN='mysql:host=127.0.0.1;dbname=fitcontrol;charset=utf8mb4'
   export DB_USER='fitcontrol'
   read -rsp 'Senha do MySQL: ' DB_PASSWORD
   printf '\n'
   export DB_PASSWORD
   ```

3. Crie a conta do Personal e inicie o servidor local.

   ```bash
   read -rsp 'Senha do Personal: ' personal_password
   printf '\n'
   printf '%s\n' "$personal_password" | php bin/create-personal.php 'Nome do Personal' personal@example.com
   unset personal_password
   php -S 127.0.0.1:8000 -t public
   ```

4. Abra [Fitcontrol local](http://127.0.0.1:8000) e entre com a conta criada.

O fuso horário padrão é `America/Sao_Paulo`. Use `APP_TIMEZONE` para alterá-lo. Na hospedagem, aponte a raiz pública exclusivamente para `public/`, configure HTTPS e ative `APP_SECURE_COOKIE=1` no ambiente do PHP, especialmente quando houver proxy reverso.

## Fluxos

- **Personal** gerencia seus próprios alunos, exercícios, treinos e mensalidades. Ao cadastrar um aluno, define também as credenciais de acesso.
- **Aluno** consulta a própria ficha ativa e o próprio histórico financeiro em modo de leitura.
- **Treinos** são organizados em A, B e C. Ao salvar uma nova ficha ou uma renovação, a ficha passa a ser ativa e a anterior fica no histórico.
- **Mensalidades** são lançadas manualmente por aluno e mês. O vencimento usa o dia do cadastro, limitado ao último dia do mês. Preencha a data de pagamento para registrar o recebimento. A aplicação identifica mensalidades em aberto como pendentes ou vencidas.

## Estrutura

- `public/` contém a entrada HTTP e os arquivos de interface.
- `app/modules/` contém os módulos de alunos, exercícios, fichas e pagamentos.
- `app/bootstrap.php` contém a conexão PDO e funções compartilhadas.
- `database/schema.sql` contém as tabelas e restrições.
- `bin/create-personal.php` cadastra o perfil Personal pelo terminal.
