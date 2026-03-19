// admin/users.js

export async function renderUsersEditor(ctx) {
    const { workspaceEl } = ctx;
    workspaceEl.innerHTML = `<h3>System Users</h3><p>Loading users...</p>`;
    
    try {
        const res = await fetch('api.php?action=users_list');
        const data = await res.json();
        
        if (data.status !== 'success') {
            workspaceEl.innerHTML = `<h3 style="color:red;">Error</h3><p>${data.error}</p>`;
            return;
        }
        
        let html = `
            <h3>System Users Management</h3>
            <p style="color: #777; margin-bottom: 20px;">
                Manage user access to the frontend application. Add new accounts or deactivate existing ones.
            </p>
            <table style="width:100%; border-collapse: collapse; margin-bottom: 30px; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid #cbd5e1;">
                        <th style="padding: 10px;">ID</th>
                        <th style="padding: 10px;">Username</th>
                        <th style="padding: 10px;">Status</th>
                        <th style="padding: 10px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        data.users.forEach(u => {
            html += `
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 10px;">${u.id}</td>
                    <td style="padding: 10px;"><strong>${u.username}</strong></td>
                    <td style="padding: 10px;">
                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; color: white; background: ${u.is_active ? '#10b981' : '#ef4444'};">
                            ${u.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td style="padding: 10px;">
                        <button class="btn-toggle-user" data-id="${u.id}" data-active="${u.is_active}" style="padding: 5px 10px; cursor: pointer; border: none; border-radius: 4px; background: ${u.is_active ? '#f59e0b' : '#3b82f6'}; color: white; font-weight: bold;">
                            ${u.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                    </td>
                </tr>
            `;
        });
        
        html += `
                </tbody>
            </table>
            
            <div style="background: #f8fafc; padding: 20px; border-radius: 6px; border: 1px solid #cbd5e1;">
                <h4 style="margin-top: 0; margin-bottom: 15px;">Add New User</h4>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Username</label>
                    <input type="text" id="newUsername" placeholder="e.g. john_doe" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Password</label>
                    <input type="password" id="newPassword" placeholder="Minimum 6 characters" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
                </div>
                <button id="btnAddUser" style="background: #10b981; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-weight: bold;">Create User</button>
            </div>
        `;
        
        workspaceEl.innerHTML = html;
        
        // Setup toggle events
        workspaceEl.querySelectorAll('.btn-toggle-user').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const id = e.target.getAttribute('data-id');
                const currentlyActive = e.target.getAttribute('data-active') === 'true';
                
                if (!confirm(`Are you sure you want to ${currentlyActive ? 'deactivate' : 'activate'} this user?`)) return;
                
                try {
                    const req = await fetch('api.php?action=users_toggle', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id, is_active: !currentlyActive })
                    });
                    const resData = await req.json();
                    
                    if (resData.status === 'success') {
                        renderUsersEditor(ctx); 
                    } else {
                        alert('Error: ' + resData.error);
                    }
                } catch (err) {
                    alert('Network error occurred.');
                }
            });
        });
        
        // Setup user creation
        document.getElementById('btnAddUser').addEventListener('click', async () => {
            const username = document.getElementById('newUsername').value;
            const password = document.getElementById('newPassword').value;
            
            if (!username || !password) {
                alert('Username and password are required!');
                return;
            }
            
            try {
                const req = await fetch('api.php?action=users_add', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                const resData = await req.json();
                
                if (resData.status === 'success') {
                    alert('User created successfully!');
                    renderUsersEditor(ctx); 
                } else {
                    alert('Error: ' + resData.error);
                }
            } catch (err) {
                alert('Network error occurred.');
            }
        });
        
    } catch (e) {
        workspaceEl.innerHTML = `<h3 style="color:red;">Network Error</h3><p>${e.message}</p>`;
    }
}