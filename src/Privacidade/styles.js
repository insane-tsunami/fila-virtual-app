import styled from 'styled-components';

export const Pagina = styled.main`
  min-height: 100vh;
  display: flex;
  justify-content: center;
  padding: 32px 16px;
  background: #1a1a2e;
`;

export const Cartao = styled.section`
  width: 100%;
  max-width: 720px;
  padding: 32px;
  border-radius: 8px;
  background: #ffffff;
  color: #323c47;

  h1 {
    font-size: 26px;
    margin-bottom: 16px;
  }

  h2 {
    font-size: 18px;
    margin: 24px 0 8px;
  }

  p,
  li {
    line-height: 1.6;
    margin-bottom: 8px;
  }

  ul {
    padding-left: 20px;
  }

  a {
    color: #12014f;
  }
`;
