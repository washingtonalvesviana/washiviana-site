import type { Metadata } from "next";
import { Nunito } from "next/font/google";
import "./globals.css";

const nunito = Nunito({
    subsets: ["latin"],
    variable: "--font-nunito",
});

export const metadata: Metadata = {
    title: "WashiViana | IA Industrial Tailor-Made",
    description: "Soluções de Inteligência Artificial customizadas para indústrias 4.0. Da eficiência operacional à vantagem competitiva.",
};

export default function RootLayout({
    children,
}: Readonly<{
    children: React.ReactNode;
}>) {
    return (
        <html lang="pt-BR" className="scroll-smooth">
            <body className={`${nunito.variable} font-sans antialiased bg-[#05101A] text-white overflow-x-hidden`}>
                {children}
            </body>
        </html>
    );
}
