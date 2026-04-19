"use client";

import { motion } from "framer-motion";
import { ShieldAlert, Fingerprint, Lock, ShieldCheck } from "lucide-react";

export default function Security() {
    return (
        <section className="py-24 px-6 bg-[#050c14] relative overflow-hidden">
            <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-brand to-transparent opacity-20" />

            <div className="max-w-7xl mx-auto">
                <div className="text-center mb-20">
                    <motion.div
                        initial={{ opacity: 0, scale: 0.9 }}
                        whileInView={{ opacity: 1, scale: 1 }}
                        viewport={{ once: true }}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-brand/10 border border-brand/30 rounded-full text-brand text-xs font-bold uppercase tracking-widest mb-6"
                    >
                        <Fingerprint className="w-4 h-4" />
                        Cyber Security & LGPD
                    </motion.div>
                    <h2 className="text-4xl md:text-6xl font-bold mb-6 tracking-tight">
                        SEGURANÇA POR DESIGN. <br />
                        <span className="text-accent underline decoration-white/10 decoration-2 underline-offset-8 font-black"> IA SEM TABUS.</span>
                    </h2>
                    <p className="text-xl text-gray-500 max-w-3xl mx-auto">
                        Garantimos que seus dados estratégicos permaneçam sob seu controle total, com protocolos de criptografia e conformidade rigorosa.
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    {[
                        {
                            title: "Privacidade Garantida",
                            desc: "APIs criptografadas com contratos de não-treinamento (Enterprise Privacy).",
                            icon: ShieldCheck,
                            highlight: false
                        },
                        {
                            title: "Modelo Local/Híbrido",
                            desc: "A IA roda dentro do seu servidor. Nenhum dado sensível sai da fábrica.",
                            icon: Lock,
                            highlight: true
                        },
                        {
                            title: "Conformidade LGPD",
                            desc: "Sistemas estruturados sob as normas de proteção de dados brasileiras.",
                            icon: ShieldAlert,
                            highlight: false
                        },
                        {
                            title: "Criptografia E2E",
                            desc: "Tráfego de informações protegido de ponta a ponta.",
                            icon: Fingerprint,
                            highlight: false
                        }
                    ].map((item, index) => (
                        <motion.div
                            key={index}
                            initial={{ opacity: 0, y: 30 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true }}
                            transition={{ delay: index * 0.1 }}
                            className={`p-10 border transition-all duration-500 group relative ${item.highlight
                                    ? "border-accent bg-accent/5"
                                    : "border-white/5 bg-white/2 hover:border-brand/50"
                                }`}
                        >
                            <item.icon className={`w-12 h-12 mb-8 transition-transform group-hover:scale-110 ${item.highlight ? "text-accent" : "text-brand"
                                }`} />
                            <h3 className="text-2xl font-bold mb-4 tracking-tight">{item.title}</h3>
                            <p className="text-gray-400 font-medium leading-relaxed">
                                {item.desc}
                            </p>

                            {item.highlight && (
                                <div className="absolute top-4 right-4 animate-pulse">
                                    <div className="w-2 h-2 rounded-full bg-accent" />
                                </div>
                            )}
                        </motion.div>
                    ))}
                </div>

                <div className="mt-20 p-8 border-l-4 border-brand bg-brand/5 max-w-4xl mx-auto">
                    <p className="text-lg text-gray-300 italic font-medium">
                        &quot;Não é mais sobre usar IA, mas sobre como usar com propriedade intelectual garantida e segurança cibernética de prontidão.&quot;
                    </p>
                </div>
            </div>
        </section>
    );
}
