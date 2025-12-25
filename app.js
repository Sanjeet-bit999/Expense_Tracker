const API = "./api.php?action=";

// ----------------- PAGE SWITCH -----------------
function showPage(id) {
    document.querySelectorAll(".section").forEach(s => s.style.display = "none");

    if (id === "loginPage" || id === "registerPage") {
        document.querySelector(".sidebar").style.display = "none";
        document.querySelector(".topbar").style.display = "none";
    } else {
        document.querySelector(".sidebar").style.display = "block";
        document.querySelector(".topbar").style.display = "flex";
    }

    const page = document.getElementById(id);
    if (page) page.style.display = "block";

    if (id === "dashboardPage") loadExpenses();
    if (id === "reportPage") loadReport();
}

// ----------------- SET USER INFO -----------------
function setUserInfo(username, profilePicUrl) {
    const welcome = document.getElementById("welcome-text");
    const profile = document.getElementById("profile-img");

    if (welcome) welcome.textContent = `Welcome, ${username}`;
    if (profile) profile.src = profilePicUrl || "default.jpg";
}

// ----------------- LOGIN -----------------
function doLogin(form) {
    const data = {
        username: form.username.value,
        password: form.password.value
    };

    fetch(API + "login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(res => {
        if (res.ok) {
            setUserInfo(res.username, res.profilePic);
            showPage("dashboardPage");
        } else {
            alert(res.error);
        }
    })
    .catch(err => console.error(err));
}

// ----------------- REGISTER -----------------
function doRegister(form) {
    const data = {
        username: form.username.value,
        password: form.password.value
    };

    fetch(API + "register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(res => {
        if (res.ok) {
            setUserInfo(res.username, res.profilePic);
            showPage("dashboardPage");
        } else {
            alert(res.error);
        }
    })
    .catch(err => console.error(err));
}

// ----------------- LOGOUT -----------------
function logout() {
    fetch(API + "logout", { credentials: "include" })
        .then(res => res.json())
        .then(res => {
            if (res.ok) showPage("loginPage");
        });
}

// ----------------- EXPENSE FUNCTIONS -----------------
function loadExpenses() {
    fetch(API + "list", { credentials: "include" })
        .then(res => res.json())
        .then(data => {
            const table = document.getElementById("expTable");
            table.innerHTML = `<tr>
                <th>Item</th>
                <th>Amount</th>
                <th>Category</th>
                <th>Date</th>
                <th>Action</th>
            </tr>`;

            data.forEach(exp => {
                const row = table.insertRow();
                row.innerHTML = `
                    <td>${exp.item}</td>
                    <td>${exp.amount}</td>
                    <td>${exp.category}</td>
                    <td>${exp.created_at}</td>
                    <td>
                        <button onclick="editExpense(${exp.id})">Edit</button>
                        <button onclick="deleteExpense(${exp.id})">Delete</button>
                    </td>
                `;
            });
        });
}

function addExpense(form) {
    const data = {
        item: form.item.value,
        amount: parseFloat(form.amount.value),
        category: form.category.value
    };

    fetch(API + "add", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(res => {
        if (res.ok) {
            showPage("dashboardPage");
            loadExpenses();
        } else alert(res.error);
    });
}

function editExpense(id) {
    fetch(`${API}get&id=${id}`, { credentials: "include" })
        .then(res => res.json())
        .then(exp => {
            const form = document.getElementById("editForm");
            form.id.value = exp.id;
            form.item.value = exp.item;
            form.amount.value = exp.amount;
            form.category.value = exp.category;
            showPage("editPage");
        });
}

function updateExpense(form) {
    const data = {
        id: form.id.value,
        item: form.item.value,
        amount: parseFloat(form.amount.value),
        category: form.category.value
    };

    fetch(API + "update", {
        method: "POST",
        credentials: "include",
        body: new URLSearchParams(data)
    })
    .then(res => res.json())
    .then(res => {
        if (res.ok) {
            showPage("dashboardPage");
            loadExpenses();
        } else alert(res.error);
    });
}

function deleteExpense(id) {
    if (!confirm("Are you sure?")) return;

    fetch(API + "delete", {
        method: "POST",
        credentials: "include",
        body: new URLSearchParams({ id })
    })
    .then(res => res.json())
    .then(res => {
        if (res.ok) loadExpenses();
        else alert(res.error);
    });
}

// ----------------- REPORT -----------------
function loadReport() {
    fetch(API + "list", { credentials: "include" })
        .then(res => res.json())
        .then(data => {
            const categories = {};
            data.forEach(e => {
                categories[e.category] = (categories[e.category] || 0) + parseFloat(e.amount);
            });

            new Chart(document.getElementById("chart"), {
                type: "pie",
                data: {
                    labels: Object.keys(categories),
                    datasets: [{ data: Object.values(categories) }]
                }
            });

            const table = document.getElementById("reportTable");
            table.innerHTML = `<tr>
                <th>Item</th>
                <th>Amount</th>
                <th>Category</th>
                <th>Date</th>
            </tr>`;

            data.forEach(exp => {
                const row = table.insertRow();
                row.innerHTML = `
                    <td>${exp.item}</td>
                    <td>${exp.amount}</td>
                    <td>${exp.category}</td>
                    <td>${exp.created_at}</td>
                `;
            });
        });
}

// ----------------- EXPORT CSV -----------------
function exportCSV() {
    window.location = API + "export";
}

// ----------------- SUBMENU TOGGLE -----------------
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".submenu-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            btn.classList.toggle("active");
        });
    });

    showPage("loginPage");
});