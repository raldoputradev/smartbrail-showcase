import { Suspense, lazy } from 'react';
import TickerBand from '../components/TickerBand';
import Navbar from '../components/Navbar';
import Hero from '../components/Hero';
import ScrollToTop from '../components/ScrollToTop';

const About = lazy(() => import('../components/About'));
const HowItWorks = lazy(() => import('../components/HowItWorks'));
const Features = lazy(() => import('../components/Features'));
const Faq = lazy(() => import('../components/Faq'));
const Contact = lazy(() => import('../components/Contact'));
const CtaBanner = lazy(() => import('../components/CtaBanner'));
const SocialContact = lazy(() => import('../components/SocialContact'));
const Footer = lazy(() => import('../components/Footer'));
const ChatWidget = lazy(() => import('../components/ChatWidget'));

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
        <Suspense fallback={null}>
          <About />
          <HowItWorks />
          <Features />
          <Faq />
          <Contact map={map} />
          <CtaBanner urls={urls} apkAvailable={apkAvailable} />
        </Suspense>
      </main>
      <Suspense fallback={null}>
        <SocialContact />
        <Footer urls={urls} apkAvailable={apkAvailable} />
        <ChatWidget urls={urls} />
      </Suspense>
      <ScrollToTop />
    </>
  );
}
