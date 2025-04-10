(() => {
  const searchForms = document.querySelectorAll('form[action*="search.mass.gov"]');
  // console.log(searchForms);


  searchForms.forEach(form => {
    // form.addEventListener('submit', (event) => {
    //   event.preventDefault();
    //   const data = new FormData(form);
    //   // debugger;
    //   // const query = new URLSearchParams(data).get('q');
    //   // window.location.href = `https://search.mass.gov/?q=${query}`;
    // }, { capture: true });
  });
})()
