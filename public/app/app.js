(() => {
  "use strict";
  const $ = (id) => document.getElementById(id);
  let token = sessionStorage.getItem("rahdana_token");
  let tasks = [];
  const now = new Date();
  const today = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, "0"), String(now.getDate()).padStart(2, "0")].join("-");
  const fa = new Intl.NumberFormat("fa-IR");

  function showMessage(message, bad = false) {
    const box = $("global-message");
    box.hidden = false;
    box.textContent = message;
    box.className = "message" + (bad ? " error" : "");
  }

  async function api(path, options = {}) {
    const headers = { Accept: "application/json" };
    if (token) headers.Authorization = "Bearer " + token;
    if (options.body !== undefined) headers["Content-Type"] = "application/json";
    const response = await fetch("/api/v1" + path, {
      method: options.method || "GET",
      headers,
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
      cache: "no-store"
    });
    const payload = response.status === 204 ? {} : await response.json();
    if (!response.ok) {
      if (response.status === 401 && token && path !== "/auth/login") {
        token = null;
        sessionStorage.removeItem("rahdana_token");
        setAuth(false);
      }
      throw new Error(payload?.error?.message || "خطا در ارتباط با سرور");
    }
    return payload.data;
  }

  function setAuth(loggedIn) {
    $("auth-view").hidden = loggedIn;
    $("app-view").hidden = !loggedIn;
  }

  function taskNode(task) {
    const row = document.createElement("div");
    row.className = "task-row" + (task.status === "done" ? " done" : "");
    const check = document.createElement("input");
    check.type = "checkbox";
    check.checked = task.status === "done";
    check.setAttribute("aria-label", "تغییر وضعیت " + task.title);
    check.addEventListener("change", async () => {
      check.disabled = true;
      try {
        await api("/tasks/" + task.id, { method: "PATCH", body: { status: check.checked ? "done" : "pending" } });
        await loadDashboard();
      } catch (error) { showMessage(error.message, true); check.checked = !check.checked; }
      finally { check.disabled = false; }
    });
    const content = document.createElement("div");
    content.className = "task-text";
    const title = document.createElement("div");
    title.className = "task-title";
    title.textContent = task.title;
    const meta = document.createElement("span");
    meta.className = "task-meta";
    meta.textContent = fa.format(Number(task.estimated_minutes)) + " دقیقه" + (task.start_time ? " • " + task.start_time.slice(0, 5) : "") + (task.plan_date && task.plan_date !== today ? " • " + task.plan_date : "");
    content.append(title, meta);
    const del = document.createElement("button");
    del.className = "task-delete";
    del.type = "button";
    del.textContent = "×";
    del.title = "حذف تسک";
    del.addEventListener("click", async () => {
      if (!window.confirm("این تسک حذف شود؟")) return;
      try { await api("/tasks/" + task.id, { method: "DELETE" }); await loadDashboard(); }
      catch (error) { showMessage(error.message, true); }
    });
    row.append(check, content, del);
    return row;
  }

  function renderTasks() {
    const todayTasks = tasks.filter((x) => x.plan_date === today);
    const others = tasks.filter((x) => x.plan_date !== today);
    $("today-count").textContent = fa.format(todayTasks.length) + " فعالیت";
    for (const [containerId, items] of [["task-list", todayTasks], ["other-task-list", others]]) {
      const container = $(containerId);
      container.replaceChildren();
      if (!items.length) {
        const empty = document.createElement("p");
        empty.className = "empty";
        empty.textContent = containerId === "task-list" ? "هنوز برنامه‌ای برای امروز ثبت نکردی." : "فعالیت دیگری وجود ندارد.";
        container.append(empty);
      } else items.forEach((item) => container.append(taskNode(item)));
    }
  }

  function renderSummary(stats) {
    const overall = stats.tasks || {};
    const todayStats = stats.today || {};
    $("done-count").textContent = fa.format(Number(overall.done || 0)) + " از " + fa.format(Number(overall.total || 0));
    $("planned-time").textContent = fa.format(Number(todayStats.planned_minutes || 0)) + " دقیقه";
    $("pending-count").textContent = fa.format(Number(overall.pending || 0));
    $("active-count").textContent = fa.format(Number(overall.in_progress || 0));
    $("checkin-count").textContent = fa.format((stats.recent_checkins || []).length) + " روز";
    const checkin = stats.checkin;
    $("today-energy").textContent = checkin ? fa.format(Number(checkin.energy)) + " از ۵" : "ثبت نشده";
    $("available-time").textContent = checkin ? fa.format(Number(checkin.available_minutes) / 60) + " ساعت" : "ثبت نشده";
    if (checkin) {
      $("energy").value = checkin.energy;
      $("energy-value").textContent = fa.format(Number(checkin.energy)) + " از ۵";
      $("available-hours").value = String(Number(checkin.available_minutes) / 60);
      $("completion").value = checkin.completion;
      $("checkin-note").value = checkin.note || "";
    }
  }

  async function loadDashboard() {
    const [user, list, summary] = await Promise.all([
      api("/me"), api("/tasks"), api("/stats/summary?date=" + today)
    ]);
    $("user-name").textContent = user.name || "دوست من";
    tasks = list;
    renderTasks();
    renderSummary(summary);
  }

  $("persian-date").textContent = new Intl.DateTimeFormat("fa-IR-u-ca-persian", { weekday: "long", year: "numeric", month: "long", day: "numeric" }).format(now);

  $("login-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const button = event.currentTarget.querySelector("button");
    button.disabled = true;
    $("login-error").hidden = true;
    try {
      const result = await api("/auth/login", { method: "POST", body: { email: $("email").value, password: $("password").value } });
      token = result.token;
      sessionStorage.setItem("rahdana_token", token);
      $("password").value = "";
      setAuth(true);
      await loadDashboard();
    } catch (error) {
      $("login-error").textContent = error.message;
      $("login-error").hidden = false;
    } finally { button.disabled = false; }
  });

  $("logout").addEventListener("click", async () => {
    try { await api("/auth/logout", { method: "POST" }); } catch (_) { /* Clear locally even if network fails. */ }
    token = null; tasks = []; sessionStorage.removeItem("rahdana_token");
    setAuth(false);
  });

  $("task-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const button = event.currentTarget.querySelector("button");
    button.disabled = true;
    try {
      await api("/tasks", { method: "POST", body: {
        title: $("task-title").value.trim(), estimated_minutes: Number($("task-minutes").value), plan_date: today
      }});
      $("task-title").value = "";
      await loadDashboard();
    } catch (error) { showMessage(error.message, true); }
    finally { button.disabled = false; }
  });

  $("energy").addEventListener("input", () => { $("energy-value").textContent = fa.format(Number($("energy").value)) + " از ۵"; });

  $("checkin-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const button = event.currentTarget.querySelector("button");
    button.disabled = true;
    try {
      await api("/checkins", { method: "POST", body: {
        date: today,
        energy: Number($("energy").value),
        availableHours: Number($("available-hours").value),
        completion: $("completion").value,
        note: $("checkin-note").value
      }});
      await loadDashboard();
      showMessage("گزارش امروز ثبت شد.");
    } catch (error) { showMessage(error.message, true); }
    finally { button.disabled = false; }
  });

  $("export-context").addEventListener("click", async () => {
    try {
      const data = await api("/exports/context");
      const url = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: "application/json" }));
      const a = document.createElement("a");
      a.href = url;
      a.download = "rahdana-context-" + today + ".json";
      a.click();
      setTimeout(() => URL.revokeObjectURL(url), 10000);
    } catch (error) { showMessage(error.message, true); }
  });

  if (token) {
    setAuth(true);
    loadDashboard().catch((error) => showMessage(error.message, true));
  } else setAuth(false);
})();
