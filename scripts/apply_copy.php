#!/usr/bin/env php
<?php
/**
 * Aplica textos do copy pack (Website Copy Pack) as traducoes do site.
 *
 * - chaves "config": gravam em `configuracoes` (pt) e `configuracoes_i18n` (en/es)
 * - chaves "ui": gravam em `ui_strings` (en/es). O pt vem do fallback no template.
 *
 * Uso:
 *   php scripts/apply_copy.php --phase=1        # dry-run (padrao)
 *   php scripts/apply_copy.php --phase=1 --apply
 *   php scripts/apply_copy.php --all --apply
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/config.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Execute via CLI.\n");
    exit(1);
}

$apply = in_array('--apply', $argv, true);
$phaseArg = '1';
$all = in_array('--all', $argv, true);
foreach ($argv as $a) {
    if (strpos($a, '--phase=') === 0) $phaseArg = substr($a, 8);
}

function logLine(string $m): void {
    fwrite(STDOUT, $m . PHP_EOL);
}

// ---------------------------------------------------------------------------
// CONFIG keys: pt -> configuracoes ; en/es -> configuracoes_i18n
// ---------------------------------------------------------------------------
$config = [
    'site_subtitulo' => [
        'pt' => 'Tech & IA com linguagem humana',
        'en' => 'Tech & AI with a human touch',
        'es' => 'Tech e IA con lenguaje humano',
    ],
    'home_frase_impacto' => [
        'pt' => 'Combino mais de 29 anos de experiência em design, motion, 3D e desenvolvimento de software com fluxos de trabalho assistidos por IA para criar soluções úteis, eficientes e centradas nas pessoas.',
        'en' => 'I combine more than 29 years of experience in design, motion, 3D and software development with AI-assisted workflows to create useful, efficient and human-centered solutions.',
        'es' => 'Combino más de 29 años de experiencia en diseño, motion, 3D y desarrollo de software con flujos de trabajo asistidos por IA para crear soluciones útiles, eficientes y centradas en las personas.',
    ],
    'mini_bio' => [
        'pt' => 'Washington Viana é Creative Technologist com mais de 29 anos de experiência conectando design, desenvolvimento, produção audiovisual, mídia imersiva e fluxos de trabalho assistidos por IA.',
        'en' => 'Washington Viana is a Creative Technologist with more than 29 years of experience connecting design, development, audiovisual production, immersive media and AI-assisted workflows.',
        'es' => 'Washington Viana es Creative Technologist con más de 29 años de experiencia que conectan diseño, desarrollo, producción audiovisual, medios inmersivos y flujos de trabajo asistidos por IA.',
    ],
];

// ---------------------------------------------------------------------------
// UI keys (en/es). pt = fallback no template.
// ---------------------------------------------------------------------------
$ui = [
    // Home
    'hero.headline' => [
        'en' => 'Building digital products and experiences where creativity meets technology.',
        'es' => 'Creo productos y experiencias digitales donde la creatividad se encuentra con la tecnología.',
    ],
    'hero.eyebrow' => [
        'en' => 'Creative Technology · AI · Design · Development',
        'es' => 'Tecnología Creativa · IA · Diseño · Desarrollo',
    ],
    'hero.cta_projects' => [
        'en' => 'Explore projects',
        'es' => 'Explorar proyectos',
    ],
    'hero.cta_about' => [
        'en' => 'About my work',
        'es' => 'Sobre mi trabajo',
    ],

    // Sobre / About
    'page.about.title' => [
        'en' => 'About Washington Viana',
        'es' => 'Sobre Washington Viana',
    ],
    'about.p1' => [
        'en' => 'I am a Creative Technologist and AI-Enabled Product Developer with more than 29 years of hands-on experience across graphic design, motion design, 3D, UX/UI, audiovisual production and software development.',
        'es' => 'Soy Creative Technologist y desarrollador de productos con IA, con más de 29 años de experiencia práctica en diseño gráfico, motion design, 3D, UX/UI, producción audiovisual y desarrollo de software.',
    ],
    'about.p2' => [
        'en' => 'My career started in visual communication and expanded into digital products, web development, interactive applications, Unity, virtual reality and automation. This background helps me see a project from more than one angle: what it needs to communicate, how people will use it, and how it can be built and maintained.',
        'es' => 'Mi carrera comenzó en la comunicación visual y evolucionó hacia productos digitales, desarrollo web, aplicaciones interactivas, Unity, realidad virtual y automatización. Esa trayectoria me permite analizar un proyecto desde más de un ángulo: qué necesita comunicar, cómo lo usará la gente y cómo puede construirse y mantenerse.',
    ],
    'about.p3' => [
        'en' => 'Today, artificial intelligence is part of my daily workflow. I use it for research, planning, prototyping, coding, documentation, testing and repetitive tasks. I also build my own tools and workflows when an off-the-shelf solution does not fit the problem.',
        'es' => 'Hoy, la inteligencia artificial forma parte de mi flujo de trabajo diario. La uso para investigación, planificación, prototipado, programación, documentación, pruebas y tareas repetitivas. También creo mis propias herramientas y flujos cuando una solución lista no encaja con el problema.',
    ],
    'about.philosophy.title' => [
        'en' => 'How I work',
        'es' => 'Cómo trabajo',
    ],
    'about.philosophy.p1' => [
        'en' => 'I use AI as a performance multiplier, not as a substitute for judgment. I review outputs, test assumptions and make the final decisions. When privacy and data control matter, I prefer locally deployed models and avoid sending sensitive information to cloud services.',
        'es' => 'Uso la IA como multiplicador de rendimiento, no como sustituto del criterio profesional. Reviso, pruebo y adapto los resultados al contexto real de cada proyecto. Cuando la privacidad y el control de los datos importan, prefiero modelos ejecutados localmente y evito enviar información sensible a servicios en la nube.',
    ],
    'about.philosophy.p2' => [
        'en' => 'My work includes AI-enabled SaaS products, automation systems, web applications, UX/UI, motion graphics, 3D, 360° content and VR experiences. I am most effective in projects that need someone who can connect creative direction with technical execution.',
        'es' => 'Mi trabajo incluye productos SaaS con IA, sistemas de automatización, aplicaciones web, UX/UI, motion graphics, 3D, contenido 360° y experiencias de realidad virtual. Soy más eficaz en proyectos que necesitan a alguien capaz de conectar la dirección creativa con la ejecución técnica.',
    ],
    'about.bring.title' => [
        'en' => 'What I bring',
        'es' => 'Lo que aporto',
    ],
    'about.bring.1' => [
        'en' => 'End-to-end thinking, from the first idea to a working delivery',
        'es' => 'Visión de extremo a extremo, de la idea a la entrega funcional',
    ],
    'about.bring.2' => [
        'en' => 'Experience connecting design, communication and software',
        'es' => 'Experiencia conectando diseño, comunicación y software',
    ],
    'about.bring.3' => [
        'en' => 'AI-assisted workflows with human review and practical controls',
        'es' => 'Flujos asistidos por IA con revisión humana y controles prácticos',
    ],
    'about.bring.4' => [
        'en' => 'Experience creating reusable tools that reduce repetitive work',
        'es' => 'Creación de herramientas reutilizables para reducir tareas repetitivas',
    ],
    'about.bring.5' => [
        'en' => 'Clear communication with technical and non-technical stakeholders',
        'es' => 'Comunicación clara con equipos técnicos y no técnicos',
    ],
    'about.bring.6' => [
        'en' => 'A pragmatic approach to quality, privacy, security and delivery',
        'es' => 'Enfoque pragmático de la calidad, la privacidad, la seguridad y la entrega',
    ],
    'about.cta.title' => [
        'en' => 'Have a complex idea that needs both creative and technical thinking?',
        'es' => '¿Tienes una idea compleja que necesita pensamiento creativo y técnico a la vez?',
    ],
    'about.cta.desc' => [
        'en' => 'Get in touch and let us turn it into a clear, working solution.',
        'es' => 'Hablemos y convirtámosla en una solución clara y funcional.',
    ],
    'page.about.meta_desc' => [
        'en' => 'Creative Technologist with 29+ years connecting design, development, AI-assisted workflows, automation, 3D, motion and immersive VR experiences.',
        'es' => 'Creative Technologist con 29+ años que conectan diseño, desarrollo, flujos asistidos por IA, automatización, 3D, motion y experiencias inmersivas de VR.',
    ],

    // Automacao & IA
    'landing.automation.desc' => [
        'en' => 'I use AI and automation to remove repetitive work, improve consistency and make complex processes easier to understand. The technology matters, but the workflow around it matters just as much.',
        'es' => 'Uso la IA y la automatización para eliminar trabajo repetitivo, mejorar la consistencia y hacer que los procesos complejos sean más fáciles de entender. La tecnología importa, pero el flujo de trabajo que se construye alrededor de ella importa igual.',
    ],
    'landing.automation.how_title' => ['en' => 'How I use AI', 'es' => 'Cómo uso la IA'],
    'landing.automation.how.1' => ['en' => 'Research and information organization', 'es' => 'Investigación y organización de información'],
    'landing.automation.how.2' => ['en' => 'Product and system architecture', 'es' => 'Arquitectura de productos y sistemas'],
    'landing.automation.how.3' => ['en' => 'Prototyping and interface exploration', 'es' => 'Prototipado y exploración de interfaces'],
    'landing.automation.how.4' => ['en' => 'Software development and code review', 'es' => 'Desarrollo y revisión de software'],
    'landing.automation.how.5' => ['en' => 'Documentation and technical communication', 'es' => 'Documentación y comunicación técnica'],
    'landing.automation.how.6' => ['en' => 'Workflow automation and repetitive operations', 'es' => 'Automatización de flujos y operaciones repetitivas'],
    'landing.automation.how.7' => ['en' => 'Testing, comparison and iteration', 'es' => 'Pruebas, comparación e iteración'],
    'landing.automation.how_note' => [
        'en' => 'I do not treat generated output as finished work. I review, test and adapt it to the actual context of the project.',
        'es' => 'No considero que un resultado generado por IA sea trabajo terminado. Lo reviso, lo pruebo y lo adapto al contexto del proyecto.',
    ],
    'landing.automation.principles_title' => ['en' => 'My working principles', 'es' => 'Mis principios de trabajo'],
    'landing.automation.prin.1.title' => ['en' => 'Human judgment stays in the loop.', 'es' => 'El criterio humano permanece en el proceso.'],
    'landing.automation.prin.1.desc' => ['en' => 'AI can suggest, compare and accelerate. Responsibility for the result remains with the professional.', 'es' => 'La IA puede sugerir, comparar y acelerar. La responsabilidad del resultado sigue siendo del profesional.'],
    'landing.automation.prin.2.title' => ['en' => 'Privacy is part of the design.', 'es' => 'La privacidad forma parte del diseño.'],
    'landing.automation.prin.2.desc' => ['en' => 'Sensitive data should not be sent to a model without a clear reason, adequate controls and the necessary authorization.', 'es' => 'Los datos sensibles no deben enviarse a un modelo sin una razón clara, controles adecuados y la autorización necesaria.'],
    'landing.automation.prin.3.title' => ['en' => 'Automation should be useful.', 'es' => 'La automatización debe ser útil.'],
    'landing.automation.prin.3.desc' => ['en' => 'I automate a process when it reduces friction, improves reliability or gives people more time for work that requires judgment.', 'es' => 'Automatizo un proceso cuando reduce la fricción, mejora la fiabilidad o libera tiempo para el trabajo que requiere criterio.'],
    'landing.automation.prin.4.title' => ['en' => 'Efficiency must be measurable.', 'es' => 'La eficiencia debe ser medible.'],
    'landing.automation.prin.4.desc' => ['en' => 'In suitable workflows, I work toward at least a 30% reduction in repetitive effort. Project-specific results may be higher, but I do not present one result as a universal promise.', 'es' => 'En flujos adecuados, trabajo con una meta de reducción de al menos un 30% del esfuerzo repetitivo. Los resultados pueden ser mayores en proyectos específicos, pero no presento un resultado aislado como promesa universal.'],
    'landing.automation.multiplier_title' => ['en' => 'AI as a force multiplier', 'es' => 'La IA como multiplicador'],
    'landing.automation.multiplier_desc' => ['en' => 'My advantage is not simply knowing how to use an AI tool. It is knowing where AI helps, where it creates risk and where experience is still essential. Years of design and development practice help me ask better questions, detect weak output and make decisions that fit the project.', 'es' => 'Mi ventaja no es solo saber usar una herramienta de IA. Es saber dónde ayuda la IA, dónde crea riesgo y dónde la experiencia sigue siendo esencial. Años de práctica en diseño y desarrollo me ayudan a hacer mejores preguntas, detectar resultados débiles y tomar decisiones que encajan con el proyecto.'],
    'landing.automation.cta' => ['en' => 'Looking for practical automation or an AI-enabled workflow? Let us discuss the problem first.', 'es' => '¿Buscas automatización práctica o un flujo habilitado por IA? Hablemos primero del problema.'],

    // Design & Experiencias
    'landing.design.desc' => [
        'en' => 'I design visual and interactive experiences that make information easier to understand and more memorable. My work spans UX/UI, motion, 3D, 360° content, virtual reality and interactive applications.',
        'es' => 'Diseño experiencias visuales e interactivas que hacen que la información sea más fácil de entender y de recordar. Mi trabajo abarca UX/UI, motion, 3D, contenido 360°, realidad virtual y aplicaciones interactivas.',
    ],
    'landing.design.card_1.title' => ['en' => 'UX/UI and digital products', 'es' => 'UX/UI y productos digitales'],
    'landing.design.card_1.desc' => ['en' => 'Interfaces that balance clarity, usability and technical constraints.', 'es' => 'Interfaces que equilibran claridad, usabilidad y limitaciones técnicas.'],
    'landing.design.card_2.title' => ['en' => '3D and motion', 'es' => '3D y motion'],
    'landing.design.card_2.desc' => ['en' => 'Visual narratives, product communication, animation and large-format projection.', 'es' => 'Narrativas visuales, comunicación de producto, animación y proyecciones de gran formato.'],
    'landing.design.card_3.title' => ['en' => 'VR and immersive media', 'es' => 'VR y medios inmersivos'],
    'landing.design.card_3.desc' => ['en' => 'Experiences that combine 360° capture, spatial audio, real-time applications and physical interaction.', 'es' => 'Experiencias que combinan captura 360°, audio espacial, aplicaciones en tiempo real e interacción física.'],
    'landing.design.card_4.title' => ['en' => 'Creative engineering', 'es' => 'Ingeniería creativa'],
    'landing.design.card_4.desc' => ['en' => 'The connection between visual direction, software, hardware and the people using the experience.', 'es' => 'La conexión entre dirección visual, software, hardware y las personas que usan la experiencia.'],

    // Projetos
    'projects.page_title_all' => ['en' => 'Selected Projects', 'es' => 'Proyectos Seleccionados'],
    'projects.intro' => [
        'en' => 'These projects show how I move between creative direction, design and technical execution. Some began as communication challenges; others started as product or engineering problems. In each case, I worked to make the solution clear, usable and ready for the real context in which it would be used.',
        'es' => 'Estos proyectos muestran cómo me muevo entre la dirección creativa, el diseño y la ejecución técnica. Algunos comenzaron como desafíos de comunicación; otros, como problemas de producto o de ingeniería. En cada caso, trabajé para que la solución fuera clara, usable y lista para el contexto real en el que se usaría.',
    ],
    'projects.label.1' => ['en' => 'AI & automation', 'es' => 'IA y automatización'],
    'projects.label.2' => ['en' => 'Web and SaaS', 'es' => 'Web y SaaS'],
    'projects.label.3' => ['en' => 'UX/UI', 'es' => 'UX/UI'],
    'projects.label.4' => ['en' => '3D and motion', 'es' => '3D y motion'],
    'projects.label.5' => ['en' => 'VR and 360° experiences', 'es' => 'VR y experiencias 360°'],
    'projects.label.6' => ['en' => 'Audiovisual production', 'es' => 'Producción audiovisual'],
    'projects.feat.title' => ['en' => 'Featured projects', 'es' => 'Proyectos destacados'],
    'projects.feat.1.title' => ['en' => 'DataWise', 'es' => 'DataWise'],
    'projects.feat.1.desc' => ['en' => 'An AI-enabled, multi-tenant SaaS platform designed to unify data, automate processes and let users interact with systems through natural language. I worked across product definition, UX/UI, full-stack architecture, AI modules, governance controls and deployment. A solo delivery reached a working platform in approximately four months, compared with an estimated six to nine months for a conventional team approach.', 'es' => 'Plataforma SaaS multi-tenant habilitada por IA, diseñada para unificar datos, automatizar procesos y permitir que los usuarios interactúen con los sistemas mediante lenguaje natural. Trabajé en la definición de producto, UX/UI, arquitectura full-stack, módulos de IA, controles de gobernanza y despliegue. Una entrega en solitario alcanzó una plataforma funcional en aproximadamente cuatro meses, frente a una estimación de seis a nueve meses con un equipo convencional.'],
    'projects.feat.2.title' => ['en' => 'SEMAD | Goiás Parks VR', 'es' => 'SEMAD | Goiás Parks VR'],
    'projects.feat.2.desc' => ['en' => 'An immersive 360° VR experience covering seven state parks in Goiás. The project combined field capture, spatial audio, 3D mascots, a local multi-headset application and synchronized sensory effects such as scent, wind, water and heat. The 30-day exhibition received more than 10,000 visitors.', 'es' => 'Experiencia inmersiva en VR 360° que abarca siete parques estatales de Goiás. El proyecto combinó captura en campo, audio espacial, mascotas 3D, una aplicación local multi-headset y efectos sensoriales sincronizados como aroma, viento, agua y calor. La exposición de 30 días recibió más de 10.000 visitantes.'],
    'projects.feat.3.title' => ['en' => 'ABAL / COP30 — 3D Anamorphic Projection', 'es' => 'ABAL / COP30 — Proyección Anamórfica 3D'],
    'projects.feat.3.desc' => ['en' => 'An anamorphic projection that translated aluminum production processes into a visual narrative for a large-scale event environment. I created the 3D and post-production pipeline with Blender, After Effects and DaVinci Resolve.', 'es' => 'Proyección anamórfica que tradujo procesos de producción de aluminio en una narrativa visual para un entorno de evento a gran escala. Creé el pipeline de 3D y postproducción con Blender, After Effects y DaVinci Resolve.'],
    'projects.feat.4.title' => ['en' => 'Glowtech USA', 'es' => 'Glowtech USA'],
    'projects.feat.4.desc' => ['en' => 'An integrated brand and digital experience for solar panels designed for golf carts. The work included brand identity, a 3D mascot, motion graphics, commercials, an SEO-focused website and an interactive mobile application with 3D visualization and energy simulation.', 'es' => 'Experiencia de marca y digital integrada para paneles solares diseñados para carritos de golf. El trabajo incluyó identidad de marca, una mascota 3D, motion graphics, comerciales, un sitio web enfocado en SEO y una aplicación móvil interactiva con visualización 3D y simulación de energía.'],
    'projects.feat.5.title' => ['en' => 'Torcetex — Brand & Media', 'es' => 'Torcetex — Marca y Medios'],
    'projects.feat.5.desc' => ['en' => 'A 12-year strategic partnership covering brand positioning, digital platforms, audiovisual production and communication for a textile company working with natural fibers and sustainability. This project represents long-term collaboration rather than a single delivery.', 'es' => 'Alianza estratégica de 12 años que abarca posicionamiento de marca, plataformas digitales, producción audiovisual y comunicación para una empresa textil que trabaja con fibras naturales y sostenibilidad. Este proyecto representa colaboración a largo plazo, y no una entrega aislada.'],
    'projects.feat.6.title' => ['en' => 'Lex-Privacy AI — LGPD Privacy Platform', 'es' => 'Lex-Privacy AI — Plataforma de Privacidad LGPD'],
    'projects.feat.6.desc' => ['en' => 'A SaaS platform for personal data governance and LGPD compliance: it connects a read-only gateway to the client databases and reveals, through lineage and a risk heat map, where personal data is, where it came from and where it flows. A modular monolith (FastAPI + React 19), multi-tenant, with an autonomous agent (LLM + RAG). About 90% of the development was AI-assisted.', 'es' => 'Plataforma SaaS de gobernanza de datos personales y cumplimiento LGPD: conecta una pasarela de solo lectura a las bases del cliente y revela, mediante linaje y mapa de calor de riesgo, dónde están los datos personales, de dónde vinieron y hacia dónde fluyen. Monolito modular (FastAPI + React 19), multi-tenant, con agente autónomo (LLM + RAG). Cerca del 90% del desarrollo fue asistido por IA.'],
    'projects.feat.7.title' => ['en' => 'High-Performance Local AI Infrastructure', 'es' => 'Infraestructura de IA Local de Alto Rendimiento'],
    'projects.feat.7.desc' => ['en' => 'Private on-premise AI platform on 2 dedicated servers, in partnership with IBM AI Factory (Tier 3): a 256K-context LLM with 512 sequences and up to ~1,300 inferences/s, embeddings, RAG/OCR, media generation and 3D rendering on the same GPU. Data never leaves the company infrastructure and the marginal inference cost is ≈ 0. 120 days in production without incidents, with a redundant mirror. Configured through research, lab testing with several models and official documentation (Hugging Face, OpenAI, Anthropic, Qwen).', 'es' => 'Plataforma de IA privada on-premise en 2 servidores dedicados, en alianza con IBM AI Factory (Tier 3): LLM con contexto de 256K, 512 secuencias y hasta ~1.300 inferencias/s, embeddings, RAG/OCR, generación de medios y render 3D en la misma GPU. Los datos nunca salen de la infraestructura propia y el costo marginal de inferencia es ≈ 0. 120 días en producción sin incidencias, con espejo redundante. Configurada con base en investigación, pruebas de laboratorio con varios modelos y documentación oficial (Hugging Face, OpenAI, Anthropic, Qwen).'],

    // Tecnologias (Sobre)
    'about.tech.title' => ['en' => 'Technologies', 'es' => 'Tecnologías'],
    'about.tech.ai.title' => ['en' => 'AI and automation', 'es' => 'IA y automatización'],
    'about.tech.ai.desc' => ['en' => 'Local LLMs · Prompt engineering · Workflow automation · Data governance · Privacy-aware AI workflows', 'es' => 'LLMs locales · Ingeniería de prompts · Automatización de flujos · Gobernanza de datos · Flujos de IA con enfoque en privacidad'],
    'about.tech.dev.title' => ['en' => 'Development', 'es' => 'Desarrollo'],
    'about.tech.dev.desc' => ['en' => 'React · Next.js · TypeScript · Node.js · Python · APIs · PostgreSQL · WebSocket', 'es' => 'React · Next.js · TypeScript · Node.js · Python · APIs · PostgreSQL · WebSocket'],
    'about.tech.creative.title' => ['en' => 'Creative technology', 'es' => 'Tecnología creativa'],
    'about.tech.creative.desc' => ['en' => 'Unity · C# · VR/AR · Meta Quest · 360° content · Interactive applications', 'es' => 'Unity · C# · VR/AR · Meta Quest · Contenido 360° · Aplicaciones interactivas'],
    'about.tech.design.title' => ['en' => 'Design and media', 'es' => 'Diseño y medios'],
    'about.tech.design.desc' => ['en' => 'UX/UI · Graphic design · Motion design · Blender · Adobe Creative Cloud · After Effects · DaVinci Resolve · Figma', 'es' => 'UX/UI · Diseño gráfico · Motion design · Blender · Adobe Creative Cloud · After Effects · DaVinci Resolve · Figma'],
    'about.tech.infra.title' => ['en' => 'Infrastructure', 'es' => 'Infraestructura'],
    'about.tech.infra.desc' => ['en' => 'Docker · Kubernetes · GCP · Multi-tenant architectures · Security-conscious solution design', 'es' => 'Docker · Kubernetes · GCP · Arquitecturas multi-tenant · Soluciones con enfoque en seguridad'],
];

$runConfig = true;
$runUi = true;

if (!$apply) {
    logLine('DRY-RUN (use --apply).');
    logLine('config keys: ' . count($config) . ' | ui keys: ' . count($ui));
    foreach ($config as $k => $v) logLine("  [config] {$k}");
    foreach ($ui as $k => $v) logLine("  [ui] {$k}");
    exit(0);
}

$okC = 0; $okU = 0;

if ($runConfig) {
    $upC = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor");
    $upCi = $pdo->prepare("INSERT INTO configuracoes_i18n (chave, lang, valor) VALUES (?, ?, ?) ON CONFLICT (chave, lang) DO UPDATE SET valor = EXCLUDED.valor, updated_at = CURRENT_TIMESTAMP");
    foreach ($config as $k => $v) {
        $upC->execute([$k, $v['pt']]);
        $upCi->execute([$k, 'en', $v['en']]);
        $upCi->execute([$k, 'es', $v['es']]);
        $okC++;
    }
}

if ($runUi) {
    // ui_strings pode nao ter constraint unica (chave,lang): atualiza/insere manualmente
    foreach ($ui as $k => $langs) {
        foreach ($langs as $lang => $texto) {
            $sel = $pdo->prepare("SELECT id FROM ui_strings WHERE chave = ? AND lang = ? LIMIT 1");
            $sel->execute([$k, $lang]);
            $id = $sel->fetchColumn();
            if ($id) {
                $pdo->prepare("UPDATE ui_strings SET texto = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$texto, $id]);
            } else {
                $pdo->prepare("INSERT INTO ui_strings (chave, lang, texto) VALUES (?, ?, ?)")->execute([$k, $lang, $texto]);
            }
            $okU++;
        }
    }
}

logLine("Aplicado: {$okC} config(s), {$okU} ui string(s).");
