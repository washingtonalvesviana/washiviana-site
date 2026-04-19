"use client";

import { motion } from "framer-motion";
import { CheckCircle2, Cloud, Server, Database } from "lucide-react";

export default function Solutions() {
    return (
        <section className="py-24 px-6 relative overflow-hidden bg-[#0a1a2a]">
            {/* Dynamic Background Noise/Texture could be added here */}
            <div className="max-w-7xl mx-auto">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-20 items-center">
                    <motion.div
                        initial={{ opacity: 0, x: -50 }}
                        whileInView={{ opacity: 1, x: 0 }}
                        viewport={{ once: true }}
                        transition={{ duration: 0.8 }}
                    >
                        <h2 className="text-4xl md:text-6xl font-black mb-8 leading-[1.1] tracking-tighter">
                            A GRANDE DIFERENÇA: <br />
                            <span className="text-brand">SOLUÇÕES TAILOR-MADE</span>
                        </h2>

                        <div className="space-y-6 mb-12">
                            <p className="text-xl text-gray-300 leading-relaxed">
                                Esqueça as limitações do SaaS convencional. Com a WashiViana, você não aluga software, você constrói um <strong>ativo estratégico</strong> para sua companhia.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {[
                                { title: "Zero Assinatura", desc: "Software propriedade da empresa.", icon: CheckCircle2 },
                                { title: "Autonomia Total", desc: "Sem dependência de fornecedor.", icon: Database },
                                { title: "Infra Flexível", desc: "Cloud, Local ou Híbrido.", icon: Server },
                                { title: "Privacidade Enterprise", desc: "Dados não treinam modelos.", icon: Cloud },
                            ].map((item, id) => (
                                <div key={id} className="p-6 border border-white/5 bg-white/5 backdrop-blur-sm group hover:border-brand/40 transition-colors">
                                    <item.icon className="w-8 h-8 text-accent mb-4 group-hover:scale-110 transition-transform" />
                                    <h3 className="font-bold text-lg mb-1">{item.title}</h3>
                                    <p className="text-sm text-gray-400">{item.desc}</p>
                                </div>
                            ))}
                        </div>
                    </motion.div>

                    <motion.div
                        initial={{ opacity: 0, x: 50 }}
                        whileInView={{ opacity: 1, x: 0 }}
                        viewport={{ once: true }}
                        transition={{ duration: 0.8 }}
                        className="relative"
                    >
                        <div className="aspect-square bg-brand/10 border border-brand/20 p-8 relative">
                            <div className="absolute inset-0 bg-gradient-to-tr from-brand/20 to-transparent opacity-50" />
                            <div className="relative z-10 h-full flex flex-col justify-end">
                                <div className="text-8xl font-black text-brand/20 mb-4 tracking-tighter uppercase leading-none">
                                    Ownership
                                </div>
                                <h4 className="text-2xl font-bold mb-4 uppercase tracking-[0.2em]">O Software é seu.</h4>
                                <p className="text-gray-400 leading-relaxed font-medium">
                                    Em nosso modelo, entregamos o código-fonte e a propriedade intelectual. A empresa mantém total controle sobre o custo de manutenção e expansão.
                                </p>
                            </div>

                            {/* Decorative Geometric Elements */}
                            <div className="absolute -top-4 -right-4 w-24 h-24 border border-accent/30 animate-spin-slow" />
                            <div className="absolute top-1/2 left-0 w-full h-px bg-gradient-to-r from-transparent via-brand/30 to-transparent" />
                        </div>
                    </motion.div>
                </div>
            </div>
        </section>
    );
}
