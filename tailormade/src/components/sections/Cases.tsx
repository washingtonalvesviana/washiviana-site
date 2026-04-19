"use client";

import { motion } from "framer-motion";
import { Quote } from "lucide-react";

const cases = [
    {
        company: "Indústria Metalúrgica X",
        sector: "Manutenção Preditiva",
        challenge: "Busca manual em manuais técnicos de 40 anos consumia 4 horas/dia dos técnicos.",
        solution: "Implementação de busca semântica RAG local com privacidade total.",
        result: "Redução de 90% no tempo de diagnóstico de falhas críticas."
    },
    {
        company: "Logística Global Y",
        sector: "Supply Chain",
        challenge: "Processamento de 10.000 notas fiscais/mês com erros recorrentes de digitação.",
        solution: "IA que lê ordens de compra e valida contra o ERP autonomamente.",
        result: "Taxa de erro reduzida a zero e aceleração de 5x no ciclo financeiro."
    },
    {
        company: "Fábrica de Alimentos Z",
        sector: "Qualidade",
        challenge: "Inspeção visual humana não detectava anomalias sutis em linha rápida.",
        solution: "Detecção de anomalias com IA integrada ao sistema de câmeras existente.",
        result: "Aumento de 20% na detecção de defeitos antes do envase."
    }
];

export default function Cases() {
    return (
        <section className="py-24 px-6 bg-[#0a1018] overflow-hidden">
            <div className="max-w-7xl mx-auto">
                <div className="flex flex-col md:flex-row justify-between items-end mb-16">
                    <div className="max-w-2xl">
                        <h2 className="text-4xl md:text-6xl font-bold mb-6 tracking-tighter uppercase">
                            PROJETOS QUE <br />
                            <span className="text-brand">GERAM VALOR REAL</span>
                        </h2>
                        <p className="text-xl text-gray-500 font-medium leading-relaxed">
                            Resultados mensuráveis entregues através da aplicação pragmática de IA em problemas reais da indústria.
                        </p>
                    </div>
                    <div className="mt-8 md:mt-0">
                        <div className="text-brand font-mono text-sm tracking-widest uppercase flex items-center gap-4">
                            SELECTED_CASES_STUDY <span className="w-12 h-px bg-brand/30" />
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                    {cases.map((project, idx) => (
                        <motion.div
                            key={idx}
                            initial={{ opacity: 0, y: 30 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true }}
                            transition={{ delay: idx * 0.1 }}
                            className="group relative"
                        >
                            <div className="bg-dark border-l-2 border-brand/20 p-8 h-full flex flex-col hover:border-brand transition-all duration-500 hover:bg-brand/5">
                                <Quote className="w-8 h-8 text-brand/20 mb-6 group-hover:text-brand transition-colors" />

                                <div className="mb-6">
                                    <span className="text-[10px] uppercase tracking-[0.3em] text-accent font-black block mb-2">{project.sector}</span>
                                    <h4 className="text-2xl font-bold tracking-tight">{project.company}</h4>
                                </div>

                                <div className="space-y-6 flex-grow">
                                    <div>
                                        <span className="text-xs uppercase text-gray-600 font-bold block mb-1 tracking-widest">O Desafio</span>
                                        <p className="text-gray-400 text-sm leading-relaxed">{project.challenge}</p>
                                    </div>
                                    <div>
                                        <span className="text-xs uppercase text-gray-600 font-bold block mb-1 tracking-widest">A Solução</span>
                                        <p className="text-gray-300 text-sm leading-relaxed font-semibold">{project.solution}</p>
                                    </div>
                                </div>

                                <div className="mt-10 pt-6 border-t border-white/5">
                                    <span className="text-xs uppercase text-brand font-black block mb-2 tracking-widest underline decoration-accent/30 decoration-2 underline-offset-4">Resultado Alcançado</span>
                                    <p className="text-accent text-lg font-bold leading-tight">{project.result}</p>
                                </div>
                            </div>
                        </motion.div>
                    ))}
                </div>
            </div>
        </section>
    );
}
