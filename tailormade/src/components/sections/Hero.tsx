"use client";

import { motion } from "framer-motion";

export default function Hero() {
    return (
        <section className="relative min-h-screen flex flex-col justify-center px-6 pt-20 overflow-hidden bg-dark">
            {/* Background Decorative Element */}
            <div className="absolute top-1/4 -right-20 w-96 h-96 bg-brand opacity-10 blur-[120px] rounded-full animate-pulse" />

            <div className="relative z-10 max-w-7xl mx-auto w-full">
                <motion.div
                    initial={{ opacity: 0, y: 30 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.8, ease: "easeOut" }}
                    className="max-w-4xl"
                >
                    <span className="inline-block px-4 py-1 mb-6 border border-brand/30 text-accent text-xs font-bold tracking-[0.2em] uppercase bg-brand/5 backdrop-blur-sm">
                        WashiViana • IA Industrial
                    </span>

                    <h1 className="text-6xl md:text-8xl lg:text-9xl font-extrabold leading-[0.9] tracking-tighter mb-8 bg-gradient-to-br from-white via-white to-brand/40 bg-clip-text text-transparent">
                        IA: DA EFICIÊNCIA À VANTAGEM <br className="hidden md:block" />
                        <span className="text-accent underline decoration-brand/30 decoration-4 underline-offset-8">COMPETITIVA</span>
                    </h1>

                    <p className="text-xl md:text-2xl text-gray-400 max-w-2xl leading-relaxed mb-10 font-medium">
                        Soluções <span className="text-white italic">Tailor-Made</span> para a Indústria 4.0. Não alugue tecnologia, possua o motor da sua inovação.
                    </p>

                    <div className="flex flex-wrap gap-4">
                        <button className="px-8 py-4 bg-brand hover:bg-brand/80 text-white font-bold transition-all transform hover:-translate-y-1 active:scale-95 shadow-[0_0_20px_rgba(43,111,161,0.3)]">
                            Agendar Diagnóstico
                        </button>
                        <button className="px-8 py-4 border border-white/20 hover:border-accent text-white font-bold transition-all hover:text-accent">
                            Ver Soluções
                        </button>
                    </div>
                </motion.div>
            </div>

            {/* Decorative Branding */}
            <div className="absolute bottom-10 right-10 opacity-5 hidden md:block">
                <span className="text-8xl font-black tracking-tighter">WASHIVIANA</span>
            </div>
        </section>
    );
}
