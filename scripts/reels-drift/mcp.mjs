import { spawn } from 'node:child_process';
import { createInterface } from 'node:readline';

export class DriftClient {
  constructor(executable) {
    this.pending = new Map();
    this.sequence = 0;
    this.process = spawn(executable, ['--headless'], { windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
    this.process.stderr.on('data', data => process.stderr.write(data));
    createInterface({ input: this.process.stdout }).on('line', line => {
      let message;
      try { message = JSON.parse(line); } catch { return; }
      const waiter = this.pending.get(message.id);
      if (!waiter) return;
      this.pending.delete(message.id);
      clearTimeout(waiter.timer);
      message.error ? waiter.reject(new Error(JSON.stringify(message.error))) : waiter.resolve(message.result);
    });
    const fail = error => {
      for (const waiter of this.pending.values()) { clearTimeout(waiter.timer); waiter.reject(error); }
      this.pending.clear();
    };
    this.process.on('error', fail);
    this.process.on('exit', code => fail(new Error(`Drift encerrou: ${code}`)));
  }
  request(method, params = {}) {
    const id = ++this.sequence;
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => { this.pending.delete(id); reject(new Error(`Timeout: ${method}`)); }, 60000);
      this.pending.set(id, { resolve, reject, timer });
      this.process.stdin.write(JSON.stringify({ jsonrpc: '2.0', id, method, params }) + '\n');
    });
  }
  async initialize() {
    const result = await this.request('initialize', { protocolVersion: '2024-11-05', capabilities: {}, clientInfo: { name: 'EN-Drift-Pilot', version: '1.0' } });
    this.process.stdin.write(JSON.stringify({ jsonrpc: '2.0', method: 'notifications/initialized' }) + '\n');
    return result;
  }
  async tool(name, args = {}) {
    const result = await this.request('tools/call', { name, arguments: args });
    if (result.isError) throw new Error(JSON.stringify(result));
    return result;
  }
  close() { this.process.stdin.end(); setTimeout(() => this.process.kill(), 3000).unref(); }
}
