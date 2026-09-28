// React "islands" for Blade pages.
// Register a component below, then in Blade add:
//   <div data-react-island="ComponentName" data-props='@json($props)'></div>
// and load this entry with @vite('resources/js/islands.jsx').
// Components are loaded lazily, so a page only downloads the islands it actually renders.
import { createRoot } from 'react-dom/client';

const components = import.meta.glob('./Components/**/*.jsx');

document.querySelectorAll('[data-react-island]').forEach(async (el) => {
    const name = el.dataset.reactIsland;
    const load = components[`./Components/${name}.jsx`];

    if (!load) {
        console.warn(`React island "${name}" not found in resources/js/Components.`);
        return;
    }

    const { default: Component } = await load();

    createRoot(el).render(<Component {...JSON.parse(el.dataset.props || '{}')} />);
});
