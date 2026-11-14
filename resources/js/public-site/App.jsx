import { ThemeProvider } from './components/ThemeProvider';
import LandingPage from './pages/LandingPage';
import DownloadPage from './pages/DownloadPage';

export default function App({ page, props }) {
  return (
    <ThemeProvider>
      {page === 'download' ? (
        <DownloadPage {...props} />
      ) : (
        <LandingPage {...props} />
      )}
    </ThemeProvider>
  );
}
