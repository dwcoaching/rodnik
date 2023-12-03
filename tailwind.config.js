const defaultTheme = require('tailwindcss/defaultTheme');
import daisyui from 'daisyui';

module.exports = {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Nunito', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
        daisyui,
    ],
    daisyui: {
        themes: [
          {
            mytheme: {
              "primary": "#2563eb",
              "primary-content": "#fff",
              "secondary": "#f6d860",
              "accent": "#37cdbe",
              "neutral": "#3d4451",
              "base-100": "#ffffff",

              ".select, .input": {
                  "&-primary": {
                      "@apply border-gray-300": "",
                  },
                  "&-primary:focus": {
                      "@apply outline-offset-0 outline-0 focus-within:z-10 focus-within:ring-1 focus-within:ring-blue-600 focus-within:border-blue-600": "",
                  },
              },

              "--rounded-box": "0.5rem", // border radius rounded-box utility class, used in card and other large boxes
              "--rounded-btn": "0.5rem", // border radius rounded-btn utility class, used in buttons and similar element
              "--rounded-badge": "1.9rem", // border radius rounded-badge utility class, used in badges and similar
              "--animation-btn": "0.25s", // duration of animation when you click on button
              "--animation-input": "0.2s", // duration of animation for inputs like checkbox, toggle, radio, etc
              "--btn-text-case": "uppercase", // set default text transform for buttons
              "--btn-focus-scale": "0.95", // scale transform of button when you focus on it
              "--border-btn": "1px", // border width of buttons
              "--tab-border": "1px", // border width of tabs
              "--tab-radius": "0.5rem", // border radius of tabs
            },
          },
        ]
    }
};
