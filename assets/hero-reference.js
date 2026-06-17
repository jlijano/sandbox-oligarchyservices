(function(){
  const nav=document.getElementById("primary-navigation");
  if(nav){
    const links=[
      ["Services","/#solutions"],
      ["Industries","/#solutions"],
      ["Innovation","/ai-automation.html"],
      ["Expertise","/projects.html"],
      ["About","/about.html"],
      ["Resources","/privacy.html"]
    ];
    nav.innerHTML="";
    links.forEach(([label,href])=>{
      const link=document.createElement("a");
      link.href=href;
      link.textContent=label;
      nav.appendChild(link);
    });
    const cta=document.createElement("a");
    cta.className="nav-cta";
    cta.href="/contact.html";
    cta.textContent="Free Consultation";
    nav.appendChild(cta);
  }

  const hero=document.querySelector(".hero");
  if(hero){
    const eyebrow=hero.querySelector(".eyebrow");
    const heading=hero.querySelector("h1");
    const copy=hero.querySelector(".hero-copy");
    const primary=hero.querySelector(".button.primary");
    if(eyebrow)eyebrow.textContent="Technology services for modern operations";
    if(heading)heading.textContent="Technology + People";
    if(copy)copy.textContent="Solving technology challenges through innovation and expertise.";
    if(primary){
      primary.textContent="Get a free consultation";
      primary.href="/contact.html";
      primary.setAttribute("data-track","hero_free_consultation");
    }
  }
})();
