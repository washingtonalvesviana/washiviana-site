"use client";

import { motion } from "framer-motion";
import { Search, Cpu, ArrowRight } from "lucide-react";

export default function Pillars() {
    return (
        <section className="py-24 px-6 bg-dark overflow-hidden">
            <div className="max-w-7xl mx-auto">
                <div className="mb-20">
                    <h2 className="text-4xl md:text-6xl font-black mb-8 tracking-tighter uppercase">
                        OS DOIS PILARES DA <br />
                        <span className="text-brand">TRANSFORMAÇÃO INDUSTRIAL</span>
                    </h2>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-px bg-white/5 border border-white/5">
                    {/* Pillar 1: Data */}
                    <motion.div
                        initial={{ opacity: 0, x: -30 }}
                        whileInView={{ opacity: 1, x: 0 }}
                        viewport={{ once: true }}
                        className="bg-dark p-12 hover:bg-brand/5 transition-colors group relative"
                    >
                        <div className="text-gray-600 font-mono text-sm mb-6 flex items-center gap-2">
                            <span className="w-8 h-px bg-gray-800" /> PILLAR_01 // DATA_ORGANIZATION
                        </div>

                        <div className="flex items-start gap-6 mb-8 mt-10">
                            <div className="p-4 bg-brand/10 border border-brand/20 group-hover:border-brand transition-colors">
                                <Search className="w-10 h-10 text-brand" />
                            </div>
                            <div>
                                <h3 className="text-3xl font-bold mb-4 tracking-tight">Busca Semântica & Conhecimento</h3>
                                <p className="text-gray-400 text-lg leading-relaxed mb-6 font-medium">
                                    Transformamos manuais massivos, ordens de serviço e históricos técnicos em uma base de conhecimento instantânea e navegável.
                                </p>
                            </div>
                        </div>

                        <ul className="space-y-4 mb-10 pl-20">
                            {[
                                "Redução de 70% no tempo de busca",
                                "Entendimento de contexto (não só palavras)",
                                "Eliminação de retrabalho documental",
                                "Integração com ERP e CMMS"
                            ].map((item, id) => (
                                <li key={id} className="flex items-center gap-3 text-gray-500 font-medium">
                                    <div className="w-1.5 h-1.5 bg-brand" />
                                    {item}
                                </li>
                            ))}
                        </ul>

                        <div className="pl-20">
                            <button className="group/btn flex items-center gap-2 text-brand font-bold uppercase tracking-widest text-sm hover:text-accent transition-colors">
                                Explorar Solução <ArrowRight className="w-4 h-4 group-hover/btn:translate-x-1 transition-transform" />
                            </button>
                        </div>
                    </motion.div>

                    {/* Pillar 2: Automation */}
                    <motion.div
                        initial={{ opacity: 0, x: 30 }}
                        whileInView={{ opacity: 1, x: 0 }}
                        viewport={{ once: true }}
                        className="bg-dark p-12 hover:bg-brand/5 transition-colors group relative border-l border-white/5"
                    >
                        <div className="text-gray-600 font-mono text-sm mb-6 flex items-center gap-2">
                            <span className="w-8 h-px bg-gray-800" /> PILLAR_02 // PROCESS_INTELLIGENCE
                        </div>

                        <div className="flex items-start gap-6 mb-8 mt-10">
                            <div className="p-4 bg-accent/10 border border-accent/20 group-hover:border-accent transition-colors">
                                <Cpu className="w-10 h-10 text-accent" />
                            </div>
                            <div>
                                <h3 className="text-3xl font-bold mb-4 tracking-tight">IA que Lê, Pensa e Executa</h3>
                                <p className="text-gray-400 text-lg leading-relaxed mb-6 font-medium">
                                    Automação de processos burocráticos e técnicos com leitura autônoma de documentos e tomada de decisão rastreável.
                                </p>
                            </div>
                        </div>

                        <ul className="space-y-4 mb-10 pl-20">
                            {[
                                "Processamento de Notas e OCs",
                                "Validação inteligente de regras",
                                "Detecção de anomalias em tempo real",
                                "Rastreabilidade total de fluxos"
                            ].map((item, id) => (
                                <li key={id} className="flex items-center gap-3 text-gray-500 font-medium">
                                    <div className="w-1.5 h-1.5 bg-accent" />
                                    {item}
                                </li>
                            ))}
                        </ul>

                        <div className="pl-20">
                            <button className="group/btn flex items-center gap-2 text-accent font-bold uppercase tracking-widest text-sm hover:text-white transition-colors">
                                Explorar Solução <ArrowRight className="w-4 h-4 group-hover/btn:translate-x-1 transition-transform" />
                            </button>
                        </div>
                    </motion.div>
                </div>
            </div>
        </section>
    );
}
