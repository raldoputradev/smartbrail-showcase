import { createContext, useContext } from 'react';

const ThemeContext = createContext({ theme: 'light', toggle: () => {} });

/** Landing & download: light mode only (no dark toggle). */
export function ThemeProvider({ children }) {
  return (
    <ThemeContext.Provider value={{ theme: 'light', toggle: () => {} }}>
      <div className="min-h-screen bg-gradient-to-b from-cyan-50/80 via-blue-50/40 to-slate-100 text-slate-800">
        {children}
      </div>
    </ThemeContext.Provider>
  );
}

export function useTheme() {
  return useContext(ThemeContext);
}
