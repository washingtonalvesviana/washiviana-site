# 🚀 WASHIVIANA PORTFOLIO - Guia de Deploy

Este guia detalha o processo completo de instalação e deploy do site portfolio na **Hostinger**.

---

## 📋 Pré-requisitos

### Na Hostinger:
- ✅ Plano de hospedagem com suporte a PHP 7.4+ e MySQL
- ✅ Acesso ao painel de controle (hPanel)
- ✅ Acesso FTP/SFTP (FileZilla, WinSCP, ou similar)
- ✅ Banco de dados MySQL criado

### Localmente:
- Cliente FTP (recomendado: FileZilla)
- Editor de texto para editar configurações
- Node.js + npm (para gerar o CSS do Tailwind localmente)
- API Key da OpenAI (para funcionalidade de IA)

---

## 🗂️ Estrutura de Arquivos

Certifique-se de que todos os arquivos foram criados:

```
washiviana/
├── admin/                      # Painel administrativo
│   ├── includes/
│   │   ├── header.php
│   │   └── sidebar.php
│   ├── index.php              # Login
│   ├── dashboard.php          # Dashboard
│   ├── projetos.php           # Gerenciar projetos
│   ├── categorias.php         # Gerenciar categorias
│   ├── configuracoes.php      # Configurações
│   └── logout.php             # Logout
├── api/                        # Backend APIs
│   ├── config.php             # Configuração MySQL
│   ├── auth.php               # Autenticação
│   ├── openai.php             # Integração OpenAI
│   ├── projetos.php           # CRUD Projetos
│   ├── categorias.php         # CRUD Categorias
│   ├── upload.php             # Upload de imagens
│   ├── linkedin.php           # Posts LinkedIn
│   └── configuracoes-save.php # Salvar configs
├── assets/
│   ├── css/
│   │   ├── admin.css          # Estilos admin
│   │   └── main.css           # Estilos públicos
│   ├── js/
│   │   ├── admin.js           # Scripts admin
│   │   └── main.js            # Scripts públicos
│   └── images/
│       └── washington.jpg     # Sua foto (ADICIONAR!)
├── uploads/                    # Imagens dos projetos (criar manualmente)
├── index.php                   # Homepage
├── projetos.php                # Lista de projetos
├── projeto.php                 # Detalhes do projeto
├── database.sql                # Script do banco
├── .htaccess                   # Configurações Apache
└── README_DEPLOY.md            # Este arquivo
```

---

## 🛠️ Passo a Passo de Instalação

### **0. Gerar o CSS (Tailwind local)**

Antes de subir os arquivos, gere o CSS que o site usa (substitui o `cdn.tailwindcss.com`):

```bash
npm install
npm run build:css
```

✅ **Resultado:** o arquivo `assets/css/tailwind.min.css` é gerado/atualizado (suba ele junto no deploy). Se você alterar classes/cores, rode o build novamente e incremente o `v=` do arquivo no HTML para “quebrar cache”.

### **1. Criar Banco de Dados MySQL na Hostinger**

1. Acesse o **hPanel** da Hostinger
2. Vá em **Bases de dados** → **Gerenciador MySQL**
3. Clique em **Criar nova base de dados**
4. Anote as informações:
   - **Nome do banco:** washiviana_portfolio (ou nome escolhido)
   - **Usuário:** (será gerado automaticamente)
   - **Senha:** (defina uma senha forte)
   - **Host:** localhost

### **2. Importar Estrutura do Banco**

1. No hPanel, vá em **phpMyAdmin**
2. Selecione o banco criado
3. Clique na aba **Importar**
4. Selecione o arquivo `database.sql`
5. Clique em **Executar**

✅ **Resultado:** Todas as tabelas serão criadas com dados iniciais

### **3. Configurar Conexão com Banco de Dados**

Edite o arquivo `api/config.php` e atualize as credenciais:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'washiviana_portfolio'); // Nome do seu banco
define('DB_USER', 'seu_usuario');         // Usuário gerado
define('DB_PASS', 'sua_senha');           // Sua senha forte
define('DB_CHARSET', 'utf8mb4');
```

### **4. Configurar URLs**

No mesmo arquivo `api/config.php`, atualize a BASE_URL:

```php
define('BASE_URL', 'https://washiviana.com'); // Seu domínio real
```

### **5. Upload via FTP**

#### **Conectar via FTP:**
1. Abra FileZilla (ou cliente FTP preferido)
2. **Host:** ftp.washiviana.com (ou IP fornecido pela Hostinger)
3. **Usuário:** seu_usuario_ftp
4. **Senha:** sua_senha_ftp
5. **Porta:** 21

#### **Fazer Upload:**
1. Navegue até a pasta `public_html` no servidor
2. Faça upload de **todos os arquivos e pastas** do projeto
3. **Importante:** Mantenha a estrutura de pastas intacta

### **6. Configurar Permissões**

Via FTP, ajuste permissões da pasta `uploads`:

1. Clique com botão direito na pasta `uploads`
2. Selecione **Permissões de Arquivo**
3. Defina permissões como **755** ou **777**
4. Marque **Aplicar recursivamente a subdiretórios**

### **7. Adicionar Sua Foto**

1. Prepare a imagem da poltrona (formato JPG/PNG)
2. Renomeie para `washington.jpg`
3. Faça upload para `assets/images/washington.jpg`

### **8. Testar Instalação**

Acesse: `https://washiviana.com`

✅ **Deve exibir:** Homepage com foto e textos padrão

---

## 🔐 Primeiro Acesso ao Admin

### **1. Acessar Painel**

URL: `https://washiviana.com/admin`

### **2. Credenciais Padrão:**

```
Email: contact@washiviana.com
Senha: Washiviana@2026
```

⚠️ **IMPORTANTE:** Altere a senha imediatamente!

### **3. Configurar OpenAI API Key**

1. No admin, vá em **Configurações**
2. Insira sua **OpenAI API Key** no campo apropriado
3. Escolha o modelo (GPT-4 recomendado)
4. Clique em **Testar Conexão**
5. Salve as configurações

---

## ✏️ Personalizações Iniciais

### **1. Atualizar Textos da Homepage**

No admin → **Configurações**:
- **Título do Site:** Washington Viana
- **Subtítulo:** Interdisciplinary Creative & Developer
- **Frase de Impacto:** (sua frase personalizada)
- **Email:** contact@washiviana.com
- **Telefone:** +55 19 9 9942 2907
- **LinkedIn URL:** sua-url-linkedin

### **2. Gerenciar Categorias**

No admin → **Categorias**:
- Revisar categorias padrão
- Adicionar novas se necessário
- Reordenar conforme preferência

### **3. Criar Primeiro Projeto**

No admin → **Projetos** → **Novo Projeto**:
1. Preencha título, descrição, categoria
2. Faça upload da imagem principal
3. Adicione galeria (opcional)
4. Use o **IA Copilot** para gerar descrição e post LinkedIn
5. Marque como "Destaque" se desejar
6. Salve

---

## 🔧 Solução de Problemas

### **Erro: "Cannot connect to database"**
- ✅ Verifique credenciais em `api/config.php`
- ✅ Confirme que o banco existe no phpMyAdmin
- ✅ Teste conexão manualmente via phpMyAdmin

### **Erro: "Upload failed"**
- ✅ Verifique permissões da pasta `uploads` (775 ou 777)
- ✅ Aumente limites no `.htaccess` se necessário

### **IA Copilot não funciona**
- ✅ Verifique se API Key está configurada em Configurações
- ✅ Teste conexão usando botão "Testar Conexão"
- ✅ Confirme saldo disponível na conta OpenAI

### **Página em branco / erro 500**
- ✅ Verifique logs de erro do PHP no hPanel
- ✅ Ative `display_errors` temporariamente no `.htaccess`
- ✅ Verifique sintaxe dos arquivos PHP

### **Imagens não aparecem**
- ✅ Confirme que as imagens foram feitas upload corretamente
- ✅ Verifique permissões da pasta `uploads`
- ✅ Teste URL direta da imagem no navegador

---

## 🔒 Segurança em Produção

### **Após Deploy:**

1. **Alterar senha padrão do admin**
2. **Remover ou renomear `database.sql`** (para evitar exposição)
3. **Desabilitar display_errors** no `.htaccess`:
   ```apache
   php_flag display_errors Off
   ```
4. **Habilitar HTTPS** (descomente linhas no `.htaccess`)
5. **Backup regular** do banco de dados via phpMyAdmin

---

## 📊 Manutenção e Backups

### **Backup do Banco de Dados:**
1. Acesse phpMyAdmin
2. Selecione o banco
3. Clique em **Exportar**
4. Escolha **SQL** e baixe
5. **Frequência recomendada:** Semanal

### **Backup dos Arquivos:**
1. Via FTP, baixe toda a pasta `uploads`
2. **Frequência recomendada:** Mensal

---

## 📞 Suporte

Para dúvidas ou problemas:
- Consulte documentação da Hostinger: https://support.hostinger.com
- Verifique logs de erro no hPanel
- Teste em ambiente local primeiro (XAMPP/WAMP)

---

## ✅ Checklist de Deploy

- [ ] Banco de dados criado e importado
- [ ] Credenciais configuradas em `api/config.php`
- [ ] BASE_URL atualizada
- [ ] Todos os arquivos feitos upload via FTP
- [ ] Permissões da pasta `uploads` configuradas
- [ ] Foto `washington.jpg` adicionada
- [ ] Login admin testado
- [ ] Senha padrão alterada
- [ ] OpenAI API Key configurada
- [ ] Textos da homepage personalizados
- [ ] Primeiro projeto criado
- [ ] Site testado em diferentes navegadores
- [ ] HTTPS habilitado (se disponível)
- [ ] Backup inicial criado

---

## 🎉 Pronto!

Seu portfolio está no ar em **https://washiviana.com**

Acesse o painel admin em **https://washiviana.com/admin** para gerenciar seu conteúdo.

---

**Desenvolvido com ❤️ por Washington Viana**

