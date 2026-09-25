// React "islands" for Blade pages.
// Register a component below, then in Blade add:
//   <div data-react-island="ComponentName" data-props='@json($props)'></div>
// and load this entry with @vite('resources/js/islands.jsx').
import { createRoot } from 'react-dom/client';

const components = import.meta.glob('./Components/**/*.jsx', { eager: true });

document.querySelectorAll('[data-react-island]').forEach((el) => {
    const name = el.dataset.reactIsland;
    const Component = components[`./Components/${name}.jsx`]?.default;

    if (!Component) {
        console.warn(`React island "${name}" not found in resources/js/Components.`);
        return;
    }

    createRoot(el).render(<Component {...JSON.parse(el.dataset.props || '{}')} />);
});
