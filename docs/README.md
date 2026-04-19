# 🎨 WASHIVIANA PORTFOLIO

Site de portfólio profissional com painel administrativo completo e integração de IA para geração automática de conteúdo.

---

## ✨ Funcionalidades

### 🌐 Site Público
- **Homepage moderna** com layout split (imagem + conteúdo)
- **Galeria de projetos** com filtros por categoria
- **Página de detalhes** de cada projeto com galeria
- **Design responsivo** (mobile-first)
- **Performance otimizada** (lazy loading, cache)

### 🔐 Painel Administrativo
- **Sistema de autenticação** seguro (bcrypt)
- **Dashboard** com estatísticas e resumos
- **CRUD completo** de projetos e categorias
- **Upload de imagens** (principal + galeria)
- **Gerenciamento de configurações** do site

### 🤖 IA Copilot (OpenAI)
- **Geração automática** de descrições de projetos
- **Criação de posts** para LinkedIn
- **Prompts editáveis** e customizáveis
- **Histórico de posts** gerados
- **Integração com GPT-4**

---

## 🛠️ Stack Tecnológica

### Backend
- **PHP 7.4+** (puro, sem frameworks)
- **MySQL** (PDO para segurança)
- **OpenAI API** (GPT-4)

### Frontend
- **HTML5 + CSS3**
- **JavaScript** (vanilla, sem dependências)
- **Inter Font** (Google Fonts)

### Hospedagem
- **Compatível com Hostinger** (shared hosting)
- **Deploy via FTP/SFTP**
- **Sem necessidade de Node.js ou Python**

---

## 📁 Estrutura do Projeto

```
washiviana/
├── admin/                  # Painel administrativo
│   ├── includes/           # Header e sidebar
│   ├── index.php           # Login
│   ├── dashboard.php       # Dashboard
│   ├── projetos.php        # Gerenciar projetos + IA Copilot
│   ├── categorias.php      # Gerenciar categorias
│   ├── configuracoes.php   # Configurações gerais
│   └── logout.php          # Logout
├── api/                    # Backend APIs
│   ├── config.php          # Configuração e funções
│   ├── auth.php            # Autenticação
│   ├── openai.php          # Integração OpenAI
│   ├── projetos.php        # CRUD Projetos
│   ├── categorias.php      # CRUD Categorias
│   ├── upload.php          # Upload de imagens
│   ├── linkedin.php        # Posts do LinkedIn
│   └── configuracoes-save.php
├── assets/
│   ├── css/
│   │   ├── admin.css       # Estilos do admin
│   │   └── main.css        # Estilos públicos
│   ├── js/
│   │   ├── admin.js        # Scripts do admin + IA
│   │   └── main.js         # Scripts públicos
│   └── images/
│       └── washington.jpg  # Foto principal
├── uploads/                # Imagens dos projetos
├── index.php               # Homepage
├── projetos.php            # Lista de projetos
├── projeto.php             # Detalhes do projeto
├── database.sql            # Script SQL
├── .htaccess               # Configurações Apache
├── README.md               # Este arquivo
└── README_DEPLOY.md        # Guia de instalação
```

---

## 🚀 Instalação Rápida

### 1. Requisitos
- PHP 7.4+
- MySQL 5.7+
- Apache com mod_rewrite
- OpenAI API Key

### 2. Passos

```bash
# 1. Criar banco de dados MySQL
# 2. Importar database.sql
# 3. Editar api/config.php com credenciais
# 4. Upload via FTP para public_html
# 5. Configurar permissões da pasta uploads (755)
# 6. Acessar https://seudominio.com/admin
```

📘 **Ver guia completo:** [README_DEPLOY.md](README_DEPLOY.md)

---

## 🔑 Credenciais Padrão

**URL Admin:** `https://washiviana.com/admin`

```
Email: contact@washiviana.com
Senha: Washiviana@2026
```

⚠️ **Altere a senha após primeiro login!**

---

## 🎯 Funcionalidades Detalhadas

### Gerenciamento de Projetos
- Criar, editar e deletar projetos
- Upload de imagem principal e galeria
- Organização por categorias
- Marcar projetos em destaque
- Ativar/desativar projetos
- Adicionar tecnologias utilizadas
- Link para projeto externo

### IA Copilot
- **Gerar Descrição:** IA cria descrição profissional do projeto
- **Gerar Post LinkedIn:** IA cria post com hashtags e CTA
- **Prompts Editáveis:** Customize o prompt antes de gerar
- **Aplicar Resultado:** Insere texto gerado nos campos
- **Histórico:** Visualiza posts anteriores

### Configurações
- Editar título e subtítulo do site
- Personalizar frase de impacto da homepage
- Configurar contatos (email, telefone, LinkedIn)
- Definir OpenAI API Key
- Escolher modelo de IA (GPT-4 / GPT-3.5)
- Testar conexão com OpenAI

---

## 🎨 Design e UX

### Paleta de Cores
- **Primary:** #00BCD4 (Azul Ciano)
- **Text:** #212529 (Quase preto)
- **Light:** #f8f9fa (Cinza claro)
- **White:** #ffffff

### Typography
- **Fonte:** Inter (Google Fonts)
- **Pesos:** 300, 400, 500, 600, 700, 800, 900

### Responsividade
- **Mobile:** 320px+
- **Tablet:** 768px+
- **Desktop:** 1024px+
- **Large:** 1440px+

---

## 🔒 Segurança

### Implementado
✅ Senhas com hash bcrypt  
✅ Prepared statements (SQL injection)  
✅ Sanitização de inputs  
✅ Validação de uploads  
✅ CSRF protection  
✅ Headers de segurança (.htaccess)  
✅ Proteção de arquivos sensíveis  
✅ Sessões com timeout  

---

## 📊 Database Schema

### Tabelas

#### `usuarios`
- Administradores do sistema
- Senha criptografada com bcrypt

#### `categorias`
- Categorias dos projetos
- Ordenação customizável
- Status ativo/inativo

#### `projetos`
- Portfólio de projetos
- Imagens (principal + galeria JSON)
- Relacionamento com categorias
- Destaque e ordenação

#### `configuracoes`
- Configurações do site
- Formato chave-valor
- OpenAI API Key

#### `posts_linkedin`
- Posts gerados pela IA
- Histórico por projeto
- Prompt utilizado

---

## 🌟 Diferenciais

✨ **IA Integrada:** Copilot com OpenAI GPT-4  
✨ **100% PHP Puro:** Sem frameworks ou dependências  
✨ **Hostinger Ready:** Funciona em hospedagem compartilhada  
✨ **Mobile First:** Design responsivo moderno  
✨ **Admin Completo:** Painel intuitivo e funcional  
✨ **Performance:** Otimizado com cache e lazy loading  
✨ **Seguro:** Boas práticas de segurança implementadas  

---

## 📝 TODO / Melhorias Futuras

- [ ] Sistema de usuários múltiplos
- [ ] Editor WYSIWYG para descrições
- [ ] Publicação automática no LinkedIn via API
- [ ] Analytics integrado
- [ ] Sistema de comentários
- [ ] Newsletter
- [ ] Multi-idioma
- [ ] Dark mode
- [ ] PWA (Progressive Web App)

---

## 🤝 Contribuindo

Este é um projeto pessoal, mas sugestões são bem-vindas!

---

## 📄 Licença

© 2024 Washington Viana. Todos os direitos reservados.

---

## 📞 Contato

**Washington Viana**  
📧 Email: contact@washiviana.com  
📱 WhatsApp: +55 19 9 9942 2907  
💼 LinkedIn: [linkedin.com/in/washingtonviana](https://linkedin.com/in/washingtonviana)  
🌐 Website: [washiviana.com](https://washiviana.com)

---

**Desenvolvido com ❤️ e IA**

