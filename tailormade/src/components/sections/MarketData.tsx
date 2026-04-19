"use client";

import { motion } from "framer-motion";
import { TrendingUp, BarChart3, Clock } from "lucide-react";

const stats = [
    {
        label: "Produtividade Operational",
        value: "+40%",
        description: "Aumento direto com automação inteligente.",
        icon: TrendingUp,
    },
    {
        label: "Redução de Custos",
        value: "-30%",
        description: "Economia real em fluxos e retrabalho.",
        icon: BarChart3,
    },
    {
        label: "Mercado em Busca",
        value: "70%",
        description: "Das indústrias buscam IA em 2026.",
        icon: Clock,
    },
];

export default function MarketData() {
    return (
        <section className="py-24 px-6 bg-dark border-y border-white/5 relative">
            <div className="max-w-7xl mx-auto">
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-end mb-20">
                    <div className="lg:col-span-8">
                        <h2 className="text-4xl md:text-6xl font-bold mb-6 tracking-tight">
                            O CENÁRIO MUDOU. <br />
                            <span className="text-brand">O MERCADO NÃO ESPERA.</span>
                        </h2>
                        <p className="text-xl text-gray-400 max-w-2xl">
                            A transformação digital não é mais uma opção. É a barreira entre a relevância e a obsolescência tecnológica.
                        </p>
                    </div>
                    <div className="lg:col-span-4 text-right hidden lg:block">
                        <span className="text-gray-600 font-mono text-sm tracking-widest uppercase">
                            {'// MARKET_INTELLIGENCE_2026'}
                        </span>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-px bg-white/10">
                    {stats.map((stat, index) => (
                        <motion.div
                            key={index}
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true }}
                            transition={{ delay: index * 0.2 }}
                            className="bg-dark p-10 hover:bg-brand/5 transition-colors group"
                        >
                            <stat.icon className="w-10 h-10 text-brand mb-6 group-hover:text-accent transition-colors" />
                            <div className="text-5xl font-black mb-2 tracking-tighter text-accent">
                                {stat.value}
                            </div>
                            <div className="text-lg font-bold mb-2 uppercase tracking-wide">
                                {stat.label}
                            </div>
                            <p className="text-gray-500">
                                {stat.description}
                            </p>
                        </motion.div>
                    ))}
                </div>
            </div>
        </section>
    );
}
