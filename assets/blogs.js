(function () {
  const formatDate = (value) => {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "";
    return date.toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" });
  };

  const createFallback = () => {
    const fallback = document.createElement("span");
    fallback.setAttribute("aria-hidden", "true");
    fallback.textContent = "OS";
    return fallback;
  };

  const renderLatestBlogs = async () => {
    const section = document.querySelector("[data-latest-blogs]");
    const grid = document.querySelector("[data-latest-blogs-grid]");
    const status = document.querySelector("[data-latest-blogs-status]");
    if (!section || !grid) return;

    try {
      const response = await fetch("/api/blogs.php?limit=3", { headers: { Accept: "application/json" } });
      if (!response.ok) throw new Error("Blog API unavailable");
      const payload = await response.json();
      const posts = Array.isArray(payload.posts) ? payload.posts : [];

      if (!posts.length) {
        section.hidden = true;
        return;
      }

      grid.replaceChildren();
      posts.forEach((post) => {
        const article = document.createElement("article");
        article.className = "blog-card";

        const imageLink = document.createElement("a");
        imageLink.className = "blog-card-image";
        imageLink.href = post.url || `/blog.php?slug=${encodeURIComponent(post.slug || "")}`;
        imageLink.setAttribute("aria-label", `Read ${post.title || "blog post"}`);

        if (post.featuredImage) {
          const image = document.createElement("img");
          image.src = post.featuredImage;
          image.alt = post.title || "Blog featured image";
          image.loading = "lazy";
          image.addEventListener("error", () => imageLink.replaceChildren(createFallback()), { once: true });
          imageLink.append(image);
        } else {
          imageLink.append(createFallback());
        }

        const body = document.createElement("div");
        body.className = "blog-card-body";

        const meta = document.createElement("div");
        meta.className = "blog-meta";
        if (post.category) {
          const category = document.createElement("span");
          category.textContent = post.category;
          meta.append(category);
        }
        if (post.publishedAt) {
          const time = document.createElement("time");
          time.dateTime = String(post.publishedAt).slice(0, 10);
          time.textContent = formatDate(post.publishedAt);
          meta.append(time);
        }

        const title = document.createElement("h3");
        const titleLink = document.createElement("a");
        titleLink.href = imageLink.href;
        titleLink.textContent = post.title || "Untitled blog post";
        title.append(titleLink);

        const excerpt = document.createElement("p");
        excerpt.textContent = post.excerpt || "";

        const readMore = document.createElement("a");
        readMore.className = "blog-read-more";
        readMore.href = imageLink.href;
        readMore.textContent = "Read More";

        body.append(meta, title, excerpt, readMore);
        article.append(imageLink, body);
        grid.append(article);
      });
      if (status) status.textContent = "";
      section.hidden = false;
    } catch (error) {
      if (status) status.textContent = "Latest blog posts are temporarily unavailable.";
      section.hidden = true;
    }
  };

  const setupCopyButtons = () => {
    document.querySelectorAll("[data-copy-link]").forEach((button) => {
      button.addEventListener("click", async () => {
        const value = button.dataset.copyValue || window.location.href;
        const feedback = document.querySelector("[data-copy-feedback]");
        try {
          await navigator.clipboard.writeText(value);
          if (feedback) feedback.textContent = "Link copied.";
        } catch (error) {
          if (feedback) feedback.textContent = value;
        }
      });
    });
  };

  renderLatestBlogs();
  setupCopyButtons();
})();
