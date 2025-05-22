// Initial XML structure
let xmlString = `<tasks></tasks>`;
let parser = new DOMParser();
let serializer = new XMLSerializer();
let xmlDoc = parser.parseFromString(xmlString, "text/xml");

const form = document.getElementById("taskForm");
const taskTableBody = document.querySelector("#taskTable tbody");

form.addEventListener("submit", (e) => {
  e.preventDefault();
  addTask();
});

function addTask() {
  const name = document.getElementById("name").value.trim();
  const task = document.getElementById("task").value.trim();
  const deadline = document.getElementById("deadline").value;
  const time = document.getElementById("time").value;
  const status = document.getElementById("status").value;

  if (!name || !task || !deadline || !time) {
    alert("Please fill in all fields");
    return;
  }

  const newTask = xmlDoc.createElement("task");

  const nameEl = xmlDoc.createElement("name");
  nameEl.textContent = name;
  const descEl = xmlDoc.createElement("description");
  descEl.textContent = task;
  const deadlineEl = xmlDoc.createElement("deadline");
  deadlineEl.textContent = deadline;
  const timeEl = xmlDoc.createElement("time");
  timeEl.textContent = time;
  const statusEl = xmlDoc.createElement("status");
  statusEl.textContent = status;

  newTask.appendChild(nameEl);
  newTask.appendChild(descEl);
  newTask.appendChild(deadlineEl);
  newTask.appendChild(timeEl);
  newTask.appendChild(statusEl);

  xmlDoc.documentElement.appendChild(newTask);

  form.reset();
  renderTasks();
}

// Convert 24h time string "HH:MM" to 12h with AM/PM
function formatTime24to12(time24) {
  if (!time24) return "";
  let [hour, min] = time24.split(":").map(Number);
  let ampm = hour >= 12 ? "PM" : "AM";
  hour = hour % 12;
  if (hour === 0) hour = 12;
  return `${hour}:${min.toString().padStart(2, "0")} ${ampm}`;
}

function renderTasks() {
  taskTableBody.innerHTML = "";

  const tasks = xmlDoc.getElementsByTagName("task");
  if (tasks.length === 0) {
    return;
  }

  for (let i = 0; i < tasks.length; i++) {
    const task = tasks[i];
    const row = taskTableBody.insertRow();

    const name = task.getElementsByTagName("name")[0].textContent;
    const desc = task.getElementsByTagName("description")[0].textContent;
    const deadline = task.getElementsByTagName("deadline")[0].textContent;
    const timeRaw = task.getElementsByTagName("time")[0].textContent;
    const statusValue = task.getElementsByTagName("status")[0].textContent;

    const id = i + 1;
    const timeFormatted = formatTime24to12(timeRaw);

    let statusClass = "";
    if (statusValue === "Done") statusClass = "status-done";
    else if (statusValue === "Ongoing") statusClass = "status-ongoing";
    else if (statusValue === "Not yet started") statusClass = "status-not-started";

    row.innerHTML = `
      <td>${id}</td>
      <td>${name}</td>
      <td>${desc}</td>
      <td>${deadline}</td>
      <td>${timeFormatted}</td>
      <td class="${statusClass}">${statusValue}</td>
      <td>
        <div class="actions-menu" tabindex="0">&#x22EE;
          <div class="actions-dropdown">
            <button class="edit-btn" data-index="${i}">Edit</button>
            <button class="delete-btn" data-index="${i}">Delete</button>
          </div>
        </div>
      </td>
    `;
  }
}

function closeAllDropdownsExcept(exception) {
  document.querySelectorAll(".actions-dropdown").forEach(dropdown => {
    if (dropdown !== exception) {
      dropdown.style.display = "none";
    }
  });
}

// Toggle dropdowns on click using event delegation
taskTableBody.addEventListener("click", (e) => {
  const target = e.target;

  // Toggle dropdown menu when clicking on the 3-dot div
  if (target.classList.contains("actions-menu") || target.parentElement?.classList.contains("actions-menu")) {
    const menuDiv = target.classList.contains("actions-menu") ? target : target.parentElement;
    const dropdown = menuDiv.querySelector(".actions-dropdown");
    const isVisible = dropdown.style.display === "block";
    closeAllDropdownsExcept(isVisible ? null : dropdown);
    dropdown.style.display = isVisible ? "none" : "block";
    e.stopPropagation();
    return;
  }

  // Edit button clicked
  if (target.classList.contains("edit-btn")) {
    e.stopPropagation();
    const idx = parseInt(target.dataset.index, 10);
    editTask(idx);
    closeAllDropdownsExcept(null);
    return;
  }

  // Delete button clicked
  if (target.classList.contains("delete-btn")) {
    e.stopPropagation();
    const idx = parseInt(target.dataset.index, 10);
    deleteTask(idx);
    closeAllDropdownsExcept(null);
    return;
  }

  // Clicked outside, close all dropdowns
  closeAllDropdownsExcept(null);
});

// Also close dropdowns if clicking anywhere else on the page
window.addEventListener("click", () => {
  closeAllDropdownsExcept(null);
});

function editTask(index) {
  const task = xmlDoc.getElementsByTagName("task")[index];
  if (!task) return alert("Task not found");

  document.getElementById("name").value = task.getElementsByTagName("name")[0].textContent;
  document.getElementById("task").value = task.getElementsByTagName("description")[0].textContent;
  document.getElementById("deadline").value = task.getElementsByTagName("deadline")[0].textContent;
  document.getElementById("time").value = task.getElementsByTagName("time")[0].textContent;
  document.getElementById("status").value = task.getElementsByTagName("status")[0].textContent;

  // Remove the old task to be replaced on submit
  xmlDoc.documentElement.removeChild(task);
  renderTasks();
}

function deleteTask(index) {
  const task = xmlDoc.getElementsByTagName("task")[index];
  if (!task) return alert("Task not found");

  if (confirm("Are you sure you want to delete this task?")) {
    xmlDoc.documentElement.removeChild(task);
    renderTasks();
  }
}

// Initial render
renderTasks();
