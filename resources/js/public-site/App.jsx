import { Suspense, lazy } from 'react';
import { ThemeProvider } from './components/ThemeProvider';
import LandingPage from './pages/LandingPage';

const DownloadPage = lazy(() => import('./pages/DownloadPage'));

export default function App({ page, props }) {
  return (
    <ThemeProvider>
      {page === 'download' ? (
        <Suspense fallback={null}>
          <DownloadPage {...props} />
        </Suspense>
      ) : (
        <LandingPage {...props} />
      )}
    </ThemeProvider>
  );
}
