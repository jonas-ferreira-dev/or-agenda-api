# OR Agenda API

<p align="center">
  <strong>API de agendamentos para profissionais autônomos</strong><br />
  Backend desenvolvido em Laravel para gestão de clientes, serviços, agenda e agendamento público.
</p>

<p align="center">
  <img alt="Laravel" src="https://img.shields.io/badge/Laravel-API-red?style=for-the-badge&logo=laravel" />
  <img alt="REST" src="https://img.shields.io/badge/REST-JSON-0ea5e9?style=for-the-badge" />
  <img alt="Auth" src="https://img.shields.io/badge/Auth-Token-success?style=for-the-badge" />
  <img alt="Status" src="https://img.shields.io/badge/status-em%20desenvolvimento-7c3aed?style=for-the-badge" />
</p>

---

## ✨ Sobre o projeto

A **OR Agenda API** é uma API REST desenvolvida para atender profissionais autônomos que precisam organizar sua rotina de atendimentos de forma simples, prática e escalável.

A proposta do projeto é centralizar em um único backend as principais operações de agenda:

- cadastro e autenticação de usuários
- gerenciamento de clientes
- gerenciamento de serviços
- criação e controle de agendamentos
- exposição de perfil público para recebimento de agendamentos externos
- consulta de disponibilidade pública por profissional

Além do uso interno autenticado, a API também oferece um fluxo de **agendamento público**, permitindo que clientes finais escolham serviços, consultem horários disponíveis e solicitem um atendimento a partir de um link público com **slug** do profissional.

---

## 🎯 Objetivo

Entregar uma base sólida para sistemas de agenda voltados a:

- barbeiros
- cabeleireiros
- manicures
- designers
- tatuadores
- consultores
- prestadores de serviço em geral

A API foi pensada para servir tanto aplicações web quanto apps mobile, mantendo uma estrutura limpa de autenticação, regras de negócio e separação por usuário.

---

## 🚀 Principais funcionalidades

### Área autenticada

- Registro de usuário
- Login e logout com autenticação por token
- Consulta e atualização do perfil do usuário autenticado
- Alteração de senha
- CRUD completo de clientes
- CRUD completo de serviços
- CRUD completo de agendamentos
- Validação de conflito de horário
- Isolamento de dados por usuário autenticado

### Área pública

- Exibição de perfil profissional público por `slug`
- Listagem pública de serviços ativos do profissional
- Consulta de disponibilidade por data
- Criação de agendamento público
- Reaproveitamento automático de cliente já existente por telefone

---

## 🧱 Stack utilizada

- **PHP**
- **Laravel**
- **MySQL** *(ou outro banco compatível com Laravel, conforme sua configuração)*
- **Laravel Sanctum / autenticação por token**
- **Eloquent ORM**
- **API REST em JSON**

---

## 🏗️ Estrutura de domínio

A API trabalha, de forma geral, com as seguintes entidades:

### `User`
Usuário dono da agenda.

### `Client`
Cliente vinculado ao usuário autenticado.

### `Service`
Serviço prestado pelo profissional.

Campos esperados no serviço:
- nome
- duração em minutos
- preço
- descrição
- status ativo/inativo

### `Appointment`
Agendamento vinculado a:
- um usuário
- um cliente
- um serviço
- uma data
- horário inicial e final
- status
- observações

### `ProfessionalProfile`
Perfil público do profissional, com informações como:
- slug público
- nome público
- bio
- foto de perfil
- se o perfil está público
- se o agendamento público está habilitado

---

## 🔐 Autenticação

A autenticação é baseada em **token Bearer**.

Depois de registrar ou fazer login, a API retorna um token que deve ser enviado nas rotas protegidas:

```http
Authorization: Bearer SEU_TOKEN
```

---

## 📌 Regras de negócio importantes

### 1. Cada usuário enxerga apenas os próprios dados
Clientes, serviços e agendamentos são filtrados por `user_id`, garantindo isolamento dos registros.

### 2. Não é permitido criar agendamentos com conflito de horário
Ao criar ou atualizar um agendamento, a API valida se já existe outro atendimento no mesmo intervalo.

### 3. O agendamento público só funciona quando o perfil está liberado
O profissional precisa:
- possuir um perfil público
- estar com `is_public = true`
- estar com `booking_enabled = true`

### 4. O cliente público pode ser reaproveitado automaticamente
No fluxo público, caso já exista um cliente com o mesmo telefone, a API reaproveita esse cadastro ao invés de criar duplicidade.

### 5. Os serviços públicos podem ser limitados aos serviços ativos
No fluxo público, a listagem de serviços foi pensada para expor apenas os serviços habilitados para agendamento.

---

## 🗂️ Módulos da API

### Auth
Responsável por:
- registro
- login
- logout

### Me
Responsável por:
- exibir dados do usuário autenticado
- atualizar perfil
- atualizar senha

### Clients
Responsável por:
- listar clientes
- cadastrar cliente
- detalhar cliente
- atualizar cliente
- excluir cliente

### Services
Responsável por:
- listar serviços
- cadastrar serviço
- detalhar serviço
- atualizar serviço
- excluir serviço

### Appointments
Responsável por:
- listar agendamentos
- criar agendamento
- detalhar agendamento
- atualizar agendamento
- excluir agendamento

### Professional Profile
Responsável por:
- exibir perfil profissional do usuário autenticado
- criar perfil profissional
- atualizar perfil profissional

### Public Booking
Responsável por:
- exibir perfil público por slug
- listar serviços públicos
- consultar disponibilidade
- criar agendamento público

---

## 🌐 Sugestão de rotas

> Abaixo está uma organização sugerida para documentação da API com base na estrutura do projeto.

### Autenticação

| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/register` | Registrar novo usuário |
| POST | `/api/login` | Autenticar usuário |
| POST | `/api/logout` | Encerrar sessão/token |

### Usuário autenticado

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/me` | Retorna o usuário autenticado |
| PUT/PATCH | `/api/me` | Atualiza nome e e-mail |
| PUT/PATCH | `/api/me/password` | Atualiza senha |

### Clientes

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/clients` | Lista clientes do usuário |
| POST | `/api/clients` | Cria cliente |
| GET | `/api/clients/{id}` | Detalha cliente |
| PUT/PATCH | `/api/clients/{id}` | Atualiza cliente |
| DELETE | `/api/clients/{id}` | Remove cliente |

### Serviços

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/services` | Lista serviços |
| POST | `/api/services` | Cria serviço |
| GET | `/api/services/{id}` | Detalha serviço |
| PUT/PATCH | `/api/services/{id}` | Atualiza serviço |
| DELETE | `/api/services/{id}` | Remove serviço |

### Agendamentos

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/appointments` | Lista agendamentos |
| POST | `/api/appointments` | Cria agendamento |
| GET | `/api/appointments/{id}` | Detalha agendamento |
| PUT/PATCH | `/api/appointments/{id}` | Atualiza agendamento |
| DELETE | `/api/appointments/{id}` | Remove agendamento |

### Perfil profissional

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/professional-profile` | Exibe perfil profissional do usuário |
| POST | `/api/professional-profile` | Cria perfil profissional |
| PUT/PATCH | `/api/professional-profile` | Atualiza perfil profissional |

### Agendamento público

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/public/professionals/{slug}` | Exibe perfil público |
| GET | `/api/public/professionals/{slug}/services` | Lista serviços públicos |
| GET | `/api/public/professionals/{slug}/availability` | Consulta disponibilidade |
| POST | `/api/public/professionals/{slug}/appointments` | Cria agendamento público |

---

## 🧪 Exemplos de requisição

### Registro

```http
POST /api/register
Content-Type: application/json
```

```json
{
  "name": "Jonas Ferreira",
  "email": "jonas@email.com",
  "password": "12345678",
  "password_confirmation": "12345678"
}
```

### Login

```http
POST /api/login
Content-Type: application/json
```

```json
{
  "email": "jonas@email.com",
  "password": "12345678"
}
```

### Criar cliente

```http
POST /api/clients
Authorization: Bearer SEU_TOKEN
Content-Type: application/json
```

```json
{
  "name": "Maria Silva",
  "email": "maria@email.com",
  "phone": "11999999999",
  "notes": "Cliente recorrente"
}
```

### Criar serviço

```http
POST /api/services
Authorization: Bearer SEU_TOKEN
Content-Type: application/json
```

```json
{
  "name": "Corte de cabelo",
  "duration_minutes": 60,
  "price": 50.00,
  "description": "Corte tradicional com acabamento",
  "active": true
}
```

### Criar agendamento autenticado

```http
POST /api/appointments
Authorization: Bearer SEU_TOKEN
Content-Type: application/json
```

```json
{
  "client_id": 1,
  "service_id": 1,
  "appointment_date": "2026-04-20",
  "start_time": "10:00",
  "end_time": "11:00",
  "status": "scheduled",
  "notes": "Primeiro atendimento"
}
```

### Criar perfil profissional

```http
POST /api/professional-profile
Authorization: Bearer SEU_TOKEN
Content-Type: application/json
```

```json
{
  "slug": "jonas-studio",
  "public_name": "Jonas Studio",
  "bio": "Atendimento com foco em organização, pontualidade e experiência do cliente.",
  "profile_photo": "https://exemplo.com/foto.jpg",
  "is_public": true,
  "booking_enabled": true
}
```

### Consultar disponibilidade pública

```http
GET /api/public/professionals/jonas-studio/availability?date=2026-04-20&service_id=1
Accept: application/json
```

### Criar agendamento público

```http
POST /api/public/professionals/jonas-studio/appointments
Content-Type: application/json
```

```json
{
  "name": "Maria Silva",
  "email": "maria@email.com",
  "phone": "11999999999",
  "service_id": 1,
  "appointment_date": "2026-04-20",
  "start_time": "14:00",
  "end_time": "15:00",
  "notes": "Agendamento feito pelo link público"
}
```

---

## 📦 Como rodar o projeto localmente

> Esta seção considera o fluxo padrão de um projeto Laravel.

### 1. Clone o repositório

```bash
git clone https://github.com/SEU_USUARIO/SEU_REPOSITORIO.git
cd SEU_REPOSITORIO
```

### 2. Instale as dependências

```bash
composer install
```

### 3. Crie o arquivo `.env`

```bash
cp .env.example .env
```

### 4. Gere a chave da aplicação

```bash
php artisan key:generate
```

### 5. Configure o banco de dados no `.env`

Exemplo:

```env
APP_NAME="OR Agenda API"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=or_agenda
DB_USERNAME=root
DB_PASSWORD=
```

### 6. Rode as migrations

```bash
php artisan migrate
```

### 7. Inicie o servidor local

```bash
php artisan serve
```

A API ficará disponível em:

```txt
http://127.0.0.1:8000
```

---

## 🧪 Testes

Para executar os testes automatizados:

```bash
php artisan test
```

Se o projeto utilizar factories, seeders e banco de teste dedicado, vale configurar também um `.env.testing`.

---

## 📁 Organização sugerida do projeto

```bash
app/
 ├── Http/
 │   ├── Controllers/
 │   │   └── Api/
 │   ├── Requests/
 │   └── Middleware/
 ├── Models/

database/
 ├── factories/
 ├── migrations/
 └── seeders/

routes/
 └── api.php

tests/
 ├── Feature/
 └── Unit/
```

---

## 🔄 Fluxo resumido de uso

### Fluxo interno do profissional

1. O usuário se registra ou faz login.
2. Cadastra seus serviços.
3. Cadastra ou gerencia seus clientes.
4. Cria e acompanha seus agendamentos.
5. Configura seu perfil profissional público.

### Fluxo público do cliente final

1. O cliente acessa a URL pública do profissional.
2. Consulta os serviços disponíveis.
3. Verifica os horários livres.
4. Envia os dados para agendamento.
5. A API cria o cliente (ou reaproveita um existente) e registra o atendimento.

---

## 👨‍💻 Autor

**Jonas Ferreira**

Projeto desenvolvido para compor a suíte de soluções da **OR Digital**, com foco em organização de agenda, automação de rotina e experiência de atendimento para profissionais autônomos.



