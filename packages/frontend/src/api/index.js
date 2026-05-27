import axios from 'axios'

export function createRepoLocalApi(baseUrl = '/api/repo-local') {
  const client = axios.create({
    baseURL: baseUrl.replace(/\/$/, ''),
    timeout: 120000,
  })

  const unwrap = (res) => {
    const body = res.data
    if (!body?.success) {
      throw new Error(body?.message || 'Request failed')
    }
    return body.data
  }

  return {
    getWorkspace() {
      return client.get('/workspace').then(unwrap)
    },
    getProject(projectId) {
      return client.get(`/projects/${encodeURIComponent(projectId)}`).then(unwrap)
    },
    getTree(projectId, path = '') {
      return client.get(`/projects/${encodeURIComponent(projectId)}/tree`, { params: { path } }).then(unwrap)
    },
    getFile(projectId, path) {
      return client.get(`/projects/${encodeURIComponent(projectId)}/file`, { params: { path } }).then(unwrap)
    },
    getCommits(projectId, limit = 50) {
      return client.get(`/projects/${encodeURIComponent(projectId)}/commits`, { params: { limit } }).then(unwrap)
    },
    getCommit(projectId, sha) {
      return client.get(`/projects/${encodeURIComponent(projectId)}/commits/${encodeURIComponent(sha)}`).then(unwrap)
    },
    getChanges(projectId) {
      return client.get(`/projects/${encodeURIComponent(projectId)}/changes`).then(unwrap)
    },
    getDiff(projectId, { mode = 'unstaged', path = null, sha = null } = {}) {
      return client
        .get(`/projects/${encodeURIComponent(projectId)}/diff`, {
          params: { mode, path, sha },
        })
        .then(unwrap)
    },
    runTinker(projectId, { code, userId = null } = {}) {
      return client
        .post(`/projects/${encodeURIComponent(projectId)}/tinker/run`, {
          code,
          user_id: userId,
        })
        .then(unwrap)
    },
  }
}
