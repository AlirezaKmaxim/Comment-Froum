module.exports = {
  content: [
    "./src/**/*.php",
    "./*.html",
    "./assets/**/*.js"
  ],
  theme: {
    extend: {
      colors: {
        primary: '#0c2d28',
        gold: '#c39854',
        teal: '#009c8f',
        neutral: '#606060',
        border: '#d9d9d9',
        subtle: '#e0e0e0',
      },
      fontFamily: {
        peyda: ['Peyda', 'IRANSansX', 'Tahoma', 'sans-serif'],
      }
    },
  },
  plugins: [],
}
