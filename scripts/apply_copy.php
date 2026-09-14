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
        'pt' => 'Criando produtos digitais e experiências onde criatividade encontra tecnologia.',
        'en' => 'Building digital products and experiences where creativity meets technology.',
        'es' => 'Creo productos y experiencias digitales donde la creatividad se encuentra con la tecnología.',
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
