import Hero from "@/components/sections/Hero";
import MarketData from "@/components/sections/MarketData";
import Solutions from "@/components/sections/Solutions";
import Security from "@/components/sections/Security";
import Pillars from "@/components/sections/Pillars";
import Cases from "@/components/sections/Cases";
import Contact from "@/components/sections/Contact";

export default function Home() {
    return (
        <main className="min-h-screen">
            <Hero />
            <MarketData />
            <Solutions />
            <Security />
            <Pillars />
            <Cases />
            <Contact />
        </main>
    );
}
