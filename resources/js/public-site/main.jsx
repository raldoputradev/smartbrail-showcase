import '../../css/app.css';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

const el = document.getElementById('public-root');
if (el) {
  let props = {};
  try {
    props = JSON.parse(el.dataset.props || '{}');
  } catch {
    props = {};
  }
  createRoot(el).render(
    <StrictMode>
      <App page={el.dataset.page || 'landing'} props={props} />
    </StrictMode>,
  );
}
