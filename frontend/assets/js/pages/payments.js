// WiFight ISP - Payments Page

class PaymentsPage {
    static render(payments) {
        const totalAmount = payments
            .filter(p => p.status === 'completed')
            .reduce((sum, p) => sum + parseFloat(p.amount), 0);

        return `
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Payments</h2>
                    <span class="badge badge-success">Total: $${totalAmount.toFixed(2)}</span>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Plan</th>
                                <th>Date</th>
                                <th>Transaction ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${payments.map(payment => `
                                <tr>
                                    <td>${payment.id}</td>
                                    <td>${payment.username || 'N/A'}</td>
                                    <td>$${parseFloat(payment.amount).toFixed(2)}</td>
                                    <td><span class="badge badge-secondary">${payment.payment_method}</span></td>
                                    <td>
                                        <span class="badge badge-${payment.status === 'completed' ? 'success' :
                                                                   payment.status === 'pending' ? 'warning' : 'danger'}">
                                            ${payment.status}
                                        </span>
                                    </td>
                                    <td>${payment.plan_name || 'N/A'}</td>
                                    <td>${new Date(payment.created_at).toLocaleString()}</td>
                                    <td><code>${payment.transaction_id || 'N/A'}</code></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
}
