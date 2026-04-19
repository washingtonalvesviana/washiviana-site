"use client";

import { motion } from "framer-motion";
import { Mail, Phone, Globe, MessageSquare } from "lucide-react";

export default function Contact() {
    return (
        <section className="py-32 px-6 bg-dark relative overflow-hidden">
            {/* Visual Accents */}
            <div className="absolute top-0 right-0 w-1/2 h-full bg-brand/5 -skew-x-12 transform translate-x-1/2" />

            <div className="max-w-7xl mx-auto relative z-10">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-20">
                    <div>
                        <h2 className="text-5xl md:text-7xl font-black mb-10 tracking-tighter uppercase leading-[0.9]">
                            Sua Transformação <br />
                            <span className="text-brand">Começa Agora.</span>
                        </h2>
                        <p className="text-xl text-gray-400 max-w-lg mb-12 leading-relaxed">
                            O futuro da sua indústria depende de quão rápido você consegue extrair inteligência dos seus próprios dados. Vamos conversar?
                        </p>

                        <div className="space-y-8">
                            <a href="tel:19999422907" className="group flex items-center gap-6 p-4 border border-white/5 hover:border-brand transition-all">
                                <div className="p-3 bg-brand/10 group-hover:bg-brand transition-colors">
                                    <Phone className="w-6 h-6 text-brand group-hover:text-white" />
                                </div>
                                <div>
                                    <span className="block text-xs text-gray-500 uppercase font-black tracking-widest">Telefone</span>
                                    <span className="text-xl font-bold font-mono">19 9 9942-2907</span>
                                </div>
                            </a>

                            <a href="mailto:contact@washiviana.com" className="group flex items-center gap-6 p-4 border border-white/5 hover:border-brand transition-all">
                                <div className="p-3 bg-accent/10 group-hover:bg-accent transition-colors">
                                    <Mail className="w-6 h-6 text-accent group-hover:text-dark" />
                                </div>
                                <div>
                                    <span className="block text-xs text-gray-500 uppercase font-black tracking-widest">Email</span>
                                    <span className="text-xl font-bold font-mono">contact@washiviana.com</span>
                                </div>
                            </a>

                            <div className="flex items-center gap-10 pt-6">
                                <div className="flex items-center gap-3">
                                    <Globe className="w-5 h-5 text-gray-600" />
                                    <span className="text-sm font-bold text-gray-600 uppercase tracking-widest">washiviana.com</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <motion.div
                        initial={{ opacity: 0, y: 50 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        viewport={{ once: true }}
                        className="bg-white/5 backdrop-blur-xl border border-white/10 p-12 relative"
                    >
                        <div className="absolute -top-6 -left-6 bg-brand p-4 text-white">
                            <MessageSquare className="w-8 h-8" />
                        </div>

                        <h4 className="text-3xl font-bold mb-2 tracking-tight">Agende um Diagnóstico</h4>
                        <p className="text-gray-500 mb-8 font-medium">Análise preliminar de viabilidade e ROI para seu projeto de IA.</p>

                        <form className="space-y-6">
                            <div className="space-y-4">
                                <input type="text" placeholder="Nome Completo" className="w-full bg-dark/50 border border-white/10 p-4 focus:border-brand outline-none transition-colors" />
                                <input type="email" placeholder="Email Corporativo" className="w-full bg-dark/50 border border-white/10 p-4 focus:border-brand outline-none transition-colors" />
                                <textarea placeholder="Fale brevemente sobre o seu desafio..." rows={4} className="w-full bg-dark/50 border border-white/10 p-4 focus:border-brand outline-none transition-colors" />
                            </div>
                            <button className="w-full py-5 bg-brand hover:bg-white hover:text-dark text-white font-black uppercase tracking-[0.2em] transition-all transform active:scale-95 shadow-2xl">
                                Solicitar Contato Especializado
                            </button>
                        </form>
                    </motion.div>
                </div>
            </div>

            <footer className="mt-32 pt-10 border-t border-white/5 text-center text-gray-700 font-mono text-xs tracking-widest flex flex-col md:flex-row justify-between items-center gap-6 max-w-7xl mx-auto">
                <p>© 2026 WASHIVIANA • DESENVOLVIMENTO TAILOR-MADE</p>
                <p>CONSTRUINDO A INDÚSTRIA 4.0 NO BRASIL</p>
            </footer>
        </section>
    );
}
