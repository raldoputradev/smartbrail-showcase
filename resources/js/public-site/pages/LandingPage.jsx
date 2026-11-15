import TickerBand from '../components/TickerBand';
import Navbar from '../components/Navbar';
import Hero from '../components/Hero';
import About from '../components/About';
import HowItWorks from '../components/HowItWorks';
import Features from '../components/Features';
import Faq from '../components/Faq';
import Contact from '../components/Contact';
import CtaBanner from '../components/CtaBanner';
import Footer from '../components/Footer';
import SocialContact from '../components/SocialContact';
import ChatWidget from '../components/ChatWidget';
import ScrollToTop from '../components/ScrollToTop';

export default function LandingPage({
  stats,
  urls,
  map,
  appVersion,
  apkAvailable = false,
}) {
  return (
    <>
      <Navbar urls={urls} apkAvailable={apkAvailable} />
      <main>
        <Hero urls={urls} stats={stats} appVersion={appVersion} apkAvailable={apkAvailable} />
        <TickerBand />
        <About />
        <HowItWorks />
        <Features />
        <Faq />
        <Contact map={map} />
        <CtaBanner urls={urls} apkAvailable={apkAvailable} />
      </main>
      <SocialContact />
      <Footer urls={urls} apkAvailable={apkAvailable} />
      <ScrollToTop />
      <ChatWidget urls={urls} />
    </>
  );
}
